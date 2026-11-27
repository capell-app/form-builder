<?php

declare(strict_types=1);

return [
    'retained_site_submissions' => 'form submission under legal hold|form submissions under legal hold',
    'site_submissions_not_deleted' => 'form submission whose uploaded files could not be deleted|form submissions whose uploaded files could not be deleted',
    'contact_instead' => 'Contact us instead',
    'form_submitted' => 'Form submitted.',
    'form_unavailable' => 'Online enquiries are temporarily unavailable.',
    'export_form_not_found' => 'The selected form could not be found.',
    'export_written' => 'Form submissions CSV written to :path.',
    'notification_footer' => 'Open the submission in Capell to review or reply.',
    'notification_heading' => 'New form submission',
    'notification_intro' => 'A new submission was received for :form.',
    'notification_subject' => 'New submission: :form',
    'reply_recipient' => 'This reply will be sent to :email.',
    'reply_recipient_missing' => 'This submission does not include a valid email address to reply to.',
    'reply_sent' => 'Reply sent.',
    'legal_hold_applied' => 'Legal hold applied. This submission will not be deleted automatically.',
    'form_delete_blocked_by_legal_hold' => 'This form was not deleted because some of its submissions must be kept: :held under legal hold and :failed whose files could not be deleted. Its other submissions were deleted. Release the holds, or ask a developer to check the logged file errors, then delete the form again.',
    'legal_hold_released' => 'Legal hold released.',
    'legal_hold_released_submission_erased' => 'Legal hold released. This submission and its uploads were deleted, as an erasure asked while the hold was in place.',
    'submissions_delete_skipped' => 'Submissions kept: :count. They are under legal hold, their uploaded files could not be deleted, or you cannot delete them. Release any legal hold, or ask a developer to check the logged file errors, then try again.',
    'submission_delete_failed' => 'Submission kept. Its uploaded files could not be deleted, so it is marked for deletion and will be retried. Ask a developer to check the logged file errors.',
    'submission_deleted' => 'Submission deleted.',
    'status_updated' => 'Submission status updated.',
    'too_many_submissions' => 'Too many submissions. Please wait a moment and try again.',
];
