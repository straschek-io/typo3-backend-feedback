<?php

use StraschekIo\BackendFeedback\Controller\BackendFeedbackController;

return [
    'backend_feedback_form' => [
        'path' => '/backend-feedback/form',
        'methods' => ['GET'],
        'target' => BackendFeedbackController::class . '::formAction',
    ],
    'backend_feedback_send' => [
        'path' => '/backend-feedback/send',
        'methods' => ['POST'],
        'target' => BackendFeedbackController::class . '::sendAction',
    ],
];
