<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Response;

use App\Activities\Domain\Activity\Entity\Attendance;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Inscrição e presença do usuário autenticado.')]
final readonly class ViewerAttendanceResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(example: AttendanceStatus::ENROLLED, enum: AttendanceStatus::class)]
        public string $status,
        #[OA\Property(format: 'date-time')]
        public string $enrolledAt,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $checkedInAt,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $validatedAt,
    ) {
    }

    public static function fromEntity(Attendance $attendance): self
    {
        return new self(
            id: $attendance->id()->value(),
            status: $attendance->status()->value,
            enrolledAt: $attendance->enrolledAt()->toIsoStringWithMilliseconds(),
            checkedInAt: $attendance->checkedInAt()?->toIsoStringWithMilliseconds(),
            validatedAt: $attendance->validatedAt()?->toIsoStringWithMilliseconds(),
        );
    }
}
