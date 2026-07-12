<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Models;

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
use Override;

/**
 * @property int $form_id
 * @property int|null $site_id
 * @property SubmissionPayloadData $payload
 * @property SubmissionMetaData $meta
 * @property SubmissionStatus $status
 * @property Carbon $submitted_at
 * @property Form $form
 */
class Submission extends Model
{
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

    protected static string $factory = SubmissionFactory::class;

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
        ];
    }
}
