import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import RegularEvent from '@typo3/core/event/regular-event.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import Viewport from '@typo3/backend/viewport.js';
import { ModuleStateStorage } from '@typo3/backend/storage/module-state-storage.js';

/**
 * Opens the feedback form in a modal and posts it to the backend.
 */
class PermissionFeedback {
  constructor() {
    Viewport.Topbar.Toolbar.registerEvent(() => this.initializeEvents());
  }

  initializeEvents() {
    new RegularEvent('click', (event, target) => {
      event.preventDefault();
      this.openModal(target.dataset.modalTitle);
    }).delegateTo(document, '.t3js-permission-feedback-open');
  }

  /**
   * The exact view comes from the content frame, page and folder from the module state.
   * The folder state is stored as "file" in TYPO3 13 and as "media" from TYPO3 14 on.
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

    Modal.advanced({
      type: Modal.types.ajax,
      content: url.toString(),
      title: title,
      size: Modal.sizes.medium,
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

  async send(modal) {
    const form = modal.querySelector('.t3js-permission-feedback-form');
    if (!form || !form.reportValidity()) {
      return;
    }

    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.permission_feedback_send).post(new FormData(form));
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
