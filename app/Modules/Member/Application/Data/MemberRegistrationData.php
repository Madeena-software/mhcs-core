<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use App\Modules\Member\Domain\Enums\RegistrationSource;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class MemberRegistrationData
{
    /** @param list<string> $guardianMemberIds */
    /** @param list<array{namespace: string, value: string}> $externalIdentifiers */
    public function __construct(
        public string $operationId,
        public ?string $email,
        public ?string $password,
        public string $name,
        DateTimeInterface $birthDate,
        public string $administrativeGender,
        public string $nik,
        public ?string $kk,
        public ?string $phone,
        public RegistrationSource $registrationSource,
        public VerificationAssetInput $identityDocument,
        public VerificationAssetInput $profilePhoto,
        array $guardianMemberIds = [],
        array $externalIdentifiers = [],
    ) {
        if (
            trim($this->operationId) === ''
            || trim($this->name) === ''
            || trim($this->administrativeGender) === ''
            || trim($this->nik) === ''
            || ($this->email !== null && trim($this->email) === '')
            || ($this->phone !== null && trim($this->phone) === '')
        ) {
            throw new InvalidArgumentException('Member registration identity fields are required.');
        }

        $this->birthDate = DateTimeImmutable::createFromInterface($birthDate)->setTime(0, 0);
        $this->guardianMemberIds = array_values(array_unique(array_map('strval', $guardianMemberIds)));
        $this->externalIdentifiers = $externalIdentifiers;
    }

    public readonly DateTimeImmutable $birthDate;

    /** @var list<string> */
    public readonly array $guardianMemberIds;

    /** @var list<array{namespace: string, value: string}> */
    public readonly array $externalIdentifiers;
}
