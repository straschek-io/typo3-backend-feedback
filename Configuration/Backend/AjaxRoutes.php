<?php

use StraschekIo\BackendFeedback\Controller\PermissionFeedbackController;

return [
    'permission_feedback_form' => [
        'path' => '/permission-feedback/form',
        'methods' => ['GET'],
        'target' => PermissionFeedbackController::class . '::formAction',
    ],
    'permission_feedback_send' => [
        'path' => '/permission-feedback/send',
        'methods' => ['POST'],
        'target' => PermissionFeedbackController::class . '::sendAction',
    ],
];
