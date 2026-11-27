<?php

declare(strict_types=1);

return [
    'contact_instead' => 'Stattdessen Kontakt aufnehmen',
    'retained_site_submissions' => 'Formulareinsendung unter Rechtsvorbehalt|Formulareinsendungen unter Rechtsvorbehalt',
    'site_submissions_not_deleted' => 'Formulareinsendung, deren hochgeladene Dateien nicht gelöscht werden konnten|Formulareinsendungen, deren hochgeladene Dateien nicht gelöscht werden konnten',
    'form_submitted' => 'Formular gesendet.',
    'form_unavailable' => 'Online-Anfragen sind vorübergehend nicht verfügbar.',
    'export_form_not_found' => 'Das ausgewählte Formular wurde nicht gefunden.',
    'export_written' => 'CSV-Datei der Formulareinsendungen nach :path geschrieben.',
    'notification_footer' => 'Öffnen Sie die Einsendung in Capell, um sie zu prüfen oder zu beantworten.',
    'notification_heading' => 'Neue Formulareinsendung',
    'notification_intro' => 'Für :form wurde eine neue Einsendung empfangen.',
    'notification_subject' => 'Neue Einsendung: :form',
    'reply_recipient' => 'Diese Antwort wird an :email gesendet.',
    'reply_recipient_missing' => 'Diese Einsendung enthält keine gültige E-Mail-Adresse, an die geantwortet werden kann.',
    'reply_sent' => 'Antwort gesendet.',
    'legal_hold_applied' => 'Rechtliche Aufbewahrungspflicht aktiviert. Diese Einsendung wird nicht automatisch gelöscht.',
    'form_delete_blocked_by_legal_hold' => 'Dieses Formular wurde nicht gelöscht, weil einige seiner Einsendungen erhalten bleiben müssen: :held mit Aufbewahrungssperre und :failed, deren Dateien nicht gelöscht werden konnten. Die übrigen Einsendungen wurden gelöscht. Heben Sie die Sperren auf oder lassen Sie die protokollierten Dateifehler prüfen und löschen Sie das Formular dann erneut.',
    'legal_hold_released' => 'Rechtliche Aufbewahrungspflicht aufgehoben.',
    'legal_hold_released_submission_erased' => 'Rechtliche Aufbewahrungspflicht aufgehoben. Diese Einsendung und ihre Uploads wurden gelöscht, wie es eine Löschung während der Aufbewahrungspflicht verlangt hatte.',
    'submissions_delete_skipped' => 'Einsendungen behalten: :count. Sie unterliegen einer rechtlichen Aufbewahrungspflicht, oder Sie dürfen sie nicht löschen. Heben Sie eine rechtliche Aufbewahrungspflicht auf oder bitten Sie einen Entwickler, die protokollierten Dateifehler zu prüfen, und versuchen Sie es dann erneut.',
    'submission_delete_failed' => 'Einsendung behalten. Ihre hochgeladenen Dateien konnten nicht gelöscht werden; sie ist zum Löschen vorgemerkt und wird erneut versucht. Bitten Sie einen Entwickler, die protokollierten Dateifehler zu prüfen.',
    'submission_deleted' => 'Einsendung gelöscht.',
    'status_updated' => 'Status der Einsendung aktualisiert.',
    'too_many_submissions' => 'Zu viele Einsendungen. Bitte warten Sie einen Moment und versuchen Sie es erneut.',
];
