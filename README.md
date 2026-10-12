# backend_feedback

A toolbar button in the TYPO3 backend that lets every backend user send a
short message to defined recipients — "I am missing permission XY", "this
folder is locked for me". The view the user is looking at is attached
automatically, so the recipient does not have to ask where it happened.

Editors rarely know which group or permission they are missing; they only know
what they tried to do. With the module, page or folder and a link to the exact
view in the mail, an administrator can reproduce the situation in one click
and answer directly: the reporter is the Reply-To of the mail.

Runs on TYPO3 13.4 and 14.3 with PHP 8.2 to 8.5 and the PHP extensions `gd`
and `fileinfo`.

## Installation

```
composer require straschek-io/typo3-backend-feedback
vendor/bin/typo3 extension:setup
```

The button appears for all backend users as soon as the extension is active.
It does nothing useful until recipients are configured.

## Configuration

Extension configuration (Admin Tools > Settings > Extension Configuration >
`backend_feedback`):

| Setting | Default | |
|---|---|---|
| `recipients` | empty | Comma separated mail addresses that receive the feedback. Without valid recipients the form shows a notice and nothing is sent. |
| `toolbarLabel` | empty | Text of the toolbar button and title of the modal. Empty uses the translated default "Report missing permission". |
| `subjectPrefix` | empty | Start of the mail subject. Empty uses the translated default "[TYPO3 permissions]". |
| `screenshot` | on | Lets users attach a screenshot or another image to the feedback. |

A custom `toolbarLabel` or `subjectPrefix` is used as written for every
backend language; the defaults are translated (English and German).

The values live in `$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback']`
and can be set there instead, for example in `config/system/additional.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend_feedback']['recipients'] = 'admin@example.org, support@example.org';
```

Projects that map environment variables to `TYPO3_CONF_VARS` can override them
per environment, for example `TYPO3__EXTENSIONS__backend_feedback__recipients`
in a `.env` file. The configuration is read on every request, so such
overrides apply regardless of where they are set.

The sender is the system address (`MAIL/defaultMailFromAddress` and
`MAIL/defaultMailFromName`).

## The modal

A click on the toolbar button (speech bubble icon, next to the other toolbar
items) opens a medium sized modal titled like the button:

- a short introduction: describe what you wanted to do, the current view is
  attached automatically
- a read-only summary of that view: module, page with title and uid or file
  folder, and the link to the view
- one required text field "What is missing?" with an example as placeholder
- optionally a screenshot, see below
- the buttons "Cancel" and "Send"

After sending, the modal closes and a notification confirms that the
administrators have been informed. If the message is empty, no recipients are
configured or the mail cannot be sent, the modal stays open and a notification
says why.

## Screenshots

Often the quickest way to say what is missing is to show it. Below the text
field the form offers three ways to attach one picture:

- **Take screenshot** asks the browser to record the screen. The modal hides
  itself while the browser takes the picture, so it shows the view behind it.
  Chrome and Edge suggest the current tab; Firefox and Safari let the user pick
  a window or screen. The button only appears where the browser offers screen
  capture and the backend runs over HTTPS (or on localhost), which excludes
  phones and tablets. On macOS the browser needs the system permission
  "Screen Recording" once.
- **Choose image** opens the file dialog for a PNG or JPEG file.
- **Paste** an image from the clipboard while the form is open.

A preview shows what will be sent and a button removes it again. Nothing is
recorded or uploaded before the user presses "Send". Pictures wider than
1920 pixels are scaled down in the browser and large ones are converted to
JPEG, so uploads stay well below common PHP upload limits.

The server does not trust the browser. It accepts PNG and JPEG up to 5 MB,
4096 x 8192 pixels and 12 megapixels, checks size and dimensions before
decoding, and encodes the picture again with GD. Only the pixels reach the
recipients: metadata, comments and anything appended to the file are dropped.
The picture is never stored on the server, and its file name is always
`screenshot.png` or `screenshot.jpg`.

Screenshots can show personal or confidential data and reach every configured
recipient by mail. Switch the feature off with the `screenshot` setting if
that is not wanted.

## The mail

Sent as HTML and plain text, with the reporter as Reply-To (if the backend
user has a valid mail address):

- subject: prefix, name of the reporter and the first 60 characters of the message
- reporter: name, username, mail address and backend groups
- the message
- module, page (title, uid, rootline path) or file folder
- a deep link to the exact backend view, without the session bound token, and
  the frontend URL of the page
- the screenshot as attachment, if the reporter added one

The mail is written in the backend language of the reporter.

## Structure

- `Classes/Backend/ToolbarItems/BackendFeedbackToolbarItem.php`: the toolbar
  button, loads `backend-feedback.js`
- `Resources/Public/JavaScript/backend-feedback.js`: collects module,
  content frame URL, page and folder, opens the modal, takes the screenshot
  and posts the form
- `Configuration/Backend/AjaxRoutes.php`: `backend_feedback_form` (form)
  and `backend_feedback_send` (dispatch)
- `Classes/Service/FeedbackContextResolver.php`: resolves module, page, folder
  and links
- `Classes/Service/FeedbackMailer.php`: FluidEmail, with the styles from
  `Resources/Private/Css/` embedded
- `Classes/Service/FeedbackScreenshot.php`: validates the uploaded picture and
  encodes it again
- `Resources/Public/Css/backend-feedback.css`: styles of the screenshot
  part of the form
- `Classes/Configuration/ExtensionSettings.php`: the extension configuration

The mail templates are registered in `MAIL/templateRootPaths` and
`MAIL/layoutRootPaths` under key `1710` and can be overridden with a higher key.

## Development

```
./Build/dev-setup.sh
```

Bootstraps a TYPO3 14.3 dev instance (DDEV required, PHP 8.4) with two pages
and two example recipients. Backend: http://127.0.0.1:59011/typo3 (`admin` /
`BackendFeedback14!`), sent mails: `ddev mailpit`.

```
composer ci                 # all checks: validate, yaml lint, coding standards, phpstan, unit and functional tests
composer ci:php:unit        # unit tests only
composer ci:php:functional  # functional tests only, on SQLite
composer fix:php:cs         # apply coding standards fixes
```

The functional tests render the toolbar button and the form and send the mail
into a mailbox file. The GitHub Actions workflow runs unit and functional tests
on PHP 8.2, 8.3, 8.4 and 8.5, each against TYPO3 13.4 and 14.3.
