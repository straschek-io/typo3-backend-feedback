import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import RegularEvent from '@typo3/core/event/regular-event.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import Viewport from '@typo3/backend/viewport.js';
import { ModuleStateStorage } from '@typo3/backend/storage/module-state-storage.js';

const SCREENSHOT_MAXIMUM_WIDTH = 1920;
const SCREENSHOT_PNG_MAXIMUM_SIZE = 1500000;
const CAPTURING_CLASS = 'permission-feedback-capturing';

/**
 * Opens the feedback form in a modal and posts it to the backend.
 */
class PermissionFeedback {
  constructor() {
    this.modal = null;
    this.screenshot = null;
    Viewport.Topbar.Toolbar.registerEvent(() => this.initializeEvents());
  }

  initializeEvents() {
    new RegularEvent('click', (event, target) => {
      event.preventDefault();
      this.openModal(target.dataset.modalTitle);
    }).delegateTo(document, '.t3js-permission-feedback-open');

    new RegularEvent('click', () => this.captureScreenshot())
      .delegateTo(document, '.t3js-permission-feedback-screenshot-capture');
    new RegularEvent('click', () => this.setScreenshot(null))
      .delegateTo(document, '.t3js-permission-feedback-screenshot-remove');
    new RegularEvent('change', (event, target) => {
      if (target.files.length > 0) {
        this.useImage(target.files[0]);
      }
      target.value = '';
    }).delegateTo(document, '.t3js-permission-feedback-screenshot-file');
    new RegularEvent('paste', (event) => {
      const image = Array.from(event.clipboardData?.files ?? []).find((file) => file.type.startsWith('image/'));
      if (image && this.isScreenshotEnabled()) {
        event.preventDefault();
        this.useImage(image);
      }
    }).delegateTo(document, '.t3js-permission-feedback-form');
  }

  /**
   * The exact view comes from the content frame, page and folder from the module state.
   * The folder state is stored as "file" in older TYPO3 13 versions and as "media" since.
   */
  collectContext() {
    const router = document.querySelector('typo3-backend-module-router');
    const contentFrame = document.querySelector('.t3js-scaffold-content-module-iframe');
    let endpoint = router?.getAttribute('endpoint') ?? '';
    try {
      const frameLocation = contentFrame?.contentWindow?.location;
      if (frameLocation && frameLocation.pathname.startsWith('/typo3/')) {
        endpoint = frameLocation.pathname + frameLocation.search;
      }
    } catch (error) {
      // cross-origin frame, keep the router endpoint
    }

    return {
      module: router?.getAttribute('module') ?? '',
      endpoint: endpoint,
      pageId: ModuleStateStorage.current('web').identifier ?? '',
      fileIdentifier: (ModuleStateStorage.current('file').identifier || ModuleStateStorage.current('media').identifier) ?? '',
    };
  }

  openModal(title) {
    const url = new URL(TYPO3.settings.ajaxUrls.permission_feedback_form, window.location.origin);
    for (const [name, value] of Object.entries(this.collectContext())) {
      url.searchParams.set(name, value);
    }

    this.screenshot = null;
    this.modal = Modal.advanced({
      type: Modal.types.ajax,
      content: url.toString(),
      title: title,
      size: Modal.sizes.medium,
      ajaxCallback: (modal) => this.initializeScreenshot(modal),
      buttons: [
        {
          text: TYPO3.lang['modal.cancel'],
          btnClass: 'btn-default',
          trigger: (event, modal) => modal.hideModal(),
        },
        {
          text: TYPO3.lang['modal.send'],
          btnClass: 'btn-primary',
          trigger: (event, modal) => this.send(modal),
        },
      ],
    });
  }

  /**
   * Capturing needs a browser that offers it and a secure context.
   */
  initializeScreenshot(modal) {
    const captureButton = modal.querySelector('.t3js-permission-feedback-screenshot-capture');
    if (captureButton && window.isSecureContext && typeof navigator.mediaDevices?.getDisplayMedia === 'function') {
      captureButton.hidden = false;
    }
  }

  isScreenshotEnabled() {
    return this.modal?.querySelector('.t3js-permission-feedback-screenshot-preview') != null;
  }

  /**
   * The modal is hidden while the browser records, so the picture shows the view behind it.
   */
  async captureScreenshot() {
    let stream = null;
    document.body.classList.add(CAPTURING_CLASS);
    try {
      stream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false, preferCurrentTab: true });
      const video = document.createElement('video');
      video.muted = true;
      video.srcObject = stream;
      await video.play();
      // lets the browser's own dialog disappear before the frame is taken
      await new Promise((resolve) => setTimeout(resolve, 400));
      this.setScreenshot(await this.renderImage(video, video.videoWidth, video.videoHeight));
    } catch (error) {
      // dismissing the browser dialog is a decision, not an error
      if (error.name !== 'NotAllowedError' && error.name !== 'AbortError') {
        Notification.error(TYPO3.lang['notification.failed.title'], TYPO3.lang['error.screenshotCapture']);
      }
    } finally {
      stream?.getTracks().forEach((track) => track.stop());
      document.body.classList.remove(CAPTURING_CLASS);
    }
  }

  async useImage(file) {
    try {
      const bitmap = await createImageBitmap(file);
      this.setScreenshot(await this.renderImage(bitmap, bitmap.width, bitmap.height));
      bitmap.close();
    } catch (error) {
      Notification.error(TYPO3.lang['notification.failed.title'], TYPO3.lang['error.screenshotUnreadable']);
    }
  }

  /**
   * Scales the image down and prefers PNG; large pictures become JPEG to keep the upload small.
   */
  async renderImage(source, width, height) {
    const scale = Math.min(1, SCREENSHOT_MAXIMUM_WIDTH / width);
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height);

    const encode = (type, quality) => new Promise((resolve) => canvas.toBlob(resolve, type, quality));
    const png = await encode('image/png');
    if (png === null) {
      throw new Error('The image could not be encoded');
    }

    return png.size <= SCREENSHOT_PNG_MAXIMUM_SIZE ? png : await encode('image/jpeg', 0.85);
  }

  setScreenshot(screenshot) {
    this.screenshot = screenshot;
    const preview = this.modal?.querySelector('.t3js-permission-feedback-screenshot-preview');
    if (!preview) {
      return;
    }
    if (screenshot === null) {
      preview.hidden = true;
      return;
    }
    const reader = new FileReader();
    reader.addEventListener('load', () => {
      preview.querySelector('.t3js-permission-feedback-screenshot-image').src = reader.result;
      preview.hidden = false;
    });
    reader.readAsDataURL(screenshot);
  }

  async send(modal) {
    const form = modal.querySelector('.t3js-permission-feedback-form');
    if (!form || !form.reportValidity()) {
      return;
    }
    const formData = new FormData(form);
    if (this.screenshot !== null) {
      formData.set('screenshot', this.screenshot, this.screenshot.type === 'image/png' ? 'screenshot.png' : 'screenshot.jpg');
    }

    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.permission_feedback_send).post(formData);
      const result = await response.resolve();
      if (result.success) {
        Notification.success(result.title, result.message);
        modal.hideModal();
      } else {
        Notification.error(result.title, result.message);
      }
    } catch (error) {
      Notification.error(TYPO3.lang['notification.failed.title'], TYPO3.lang['error.sendFailed']);
    }
  }
}

export default new PermissionFeedback();
