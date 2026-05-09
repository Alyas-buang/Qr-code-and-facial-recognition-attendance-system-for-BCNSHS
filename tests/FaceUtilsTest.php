<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FaceUtilsTest extends TestCase
{
    public function testDescriptorNormalizeReturnsNullForWrongLength(): void
    {
        $descriptor = array_fill(0, 127, 0.1);
        $this->assertNull(face_descriptor_normalize($descriptor));
    }

    public function testDescriptorNormalizeReturnsFloatsForValidDescriptor(): void
    {
        $descriptor = array_fill(0, 128, '0.5');
        $normalized = face_descriptor_normalize($descriptor);

        $this->assertIsArray($normalized);
        $this->assertCount(128, $normalized);
        $this->assertSame(0.5, $normalized[0]);
        $this->assertIsFloat($normalized[0]);
    }

    public function testDescriptorNormComputesL2Norm(): void
    {
        $descriptor = [3.0, 4.0];
        $this->assertSame(5.0, face_descriptor_norm($descriptor));
    }

    public function testDescriptorDistanceComputesEuclideanDistance(): void
    {
        $a = [0.0, 0.0, 0.0];
        $b = [1.0, 2.0, 2.0];
        $this->assertSame(3.0, face_descriptor_distance($a, $b));
    }

    public function testNameNormalizationTrimsAndLowercases(): void
    {
        $normalized = face_name_normalize("  Alice   DELA   Cruz  ");
        $this->assertSame('alice dela cruz', $normalized);
    }
}
