<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttendanceTokenTest extends TestCase
{
    private const SECRET = 'unit-test-secret';

    public function testTokenIssueAndValidateSuccess(): void
    {
        $issuedAt = 1_700_000_000;
        $token = attendance_token_issue('2024-001', $issuedAt, self::SECRET);

        $this->assertTrue(attendance_token_valid(
            '2024-001',
            $token,
            180,
            $issuedAt + 100,
            self::SECRET
        ));
    }

    public function testTokenValidationFailsForTamperedSignature(): void
    {
        $issuedAt = 1_700_000_000;
        $token = attendance_token_issue('2024-001', $issuedAt, self::SECRET);
        $token['sig'] = 'bad-signature';

        $this->assertFalse(attendance_token_valid(
            '2024-001',
            $token,
            180,
            $issuedAt + 20,
            self::SECRET
        ));
    }

    public function testTokenValidationFailsForExpiredToken(): void
    {
        $issuedAt = 1_700_000_000;
        $token = attendance_token_issue('2024-001', $issuedAt, self::SECRET);

        $this->assertFalse(attendance_token_valid(
            '2024-001',
            $token,
            180,
            $issuedAt + 181,
            self::SECRET
        ));
    }
}
