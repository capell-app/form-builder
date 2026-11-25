<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Models;

use Capell\Core\Concerns\HasCapellTable;
use Capell\Core\Models\Site;
use Capell\FormBuilder\Casts\EncryptedDataCast;
use Capell\FormBuilder\Data\SubmissionMetaData;
use Capell\FormBuilder\Data\SubmissionPayloadData;
use Capell\FormBuilder\Database\Factories\SubmissionFactory;
use Capell\FormBuilder\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Override;

/**
 * @property int $form_id
 * @property int|null $site_id
 * @property SubmissionPayloadData $payload
 * @property SubmissionMetaData $meta
 * @property SubmissionStatus $status
 * @property Carbon $submitted_at
 * @property string|null $payment_rail
 * @property string|null $subject_email_hash
 * @property Carbon|null $delivery_pending_at
 * @property Carbon|null $delivery_failed_at
 * @property int $delivery_attempts
 * @property Carbon|null $notification_queued_at
 * @property Carbon|null $autoresponder_queued_at
 * @property Form $form
 */
class Submission extends Model
{
    use HasCapellTable;

    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'form_id',
        'site_id',
        'payload',
        'meta',
        'status',
        'submitted_at',
        'legal_hold',
        'retention_until',
    ];

    protected $hidden = ['payment_rail'];

    protected static string $factory = SubmissionFactory::class;

    /** A stable index of the submitter's address; the encrypted payload already holds the address itself. */
    public static function subjectEmailHash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    public function paymentRail(): ?string
    {
        $rail = $this->getRawOriginal('payment_rail');

        return is_string($rail) ? $rail : null;
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    #[Override]
    protected static function booted(): void
    {
        static::updating(static function (self $submission): void {
            if ($submission->isDirty('payment_rail')) {
                throw new LogicException('A submission payment rail cannot change.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'payload' => EncryptedDataCast::class . ':' . SubmissionPayloadData::class,
            'meta' => EncryptedDataCast::class . ':' . SubmissionMetaData::class,
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'legal_hold' => 'boolean',
            'retention_until' => 'datetime',
            'delivery_pending_at' => 'datetime',
            'delivery_failed_at' => 'datetime',
            'delivery_attempts' => 'integer',
            'notification_queued_at' => 'datetime',
            'autoresponder_queued_at' => 'datetime',
        ];
    }
}
