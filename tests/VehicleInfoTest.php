<?php

namespace Shekel\VinPackage\Tests;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\ValueObjects\VehicleInfo;

class VehicleInfoTest extends TestCase
{
    /**
     * Test that API validation errors are properly captured
     */
    public function testApiValidationErrorsAreCaptured()
    {
        $data = [
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => '2015',
            'validation' => [
                'error_code' => '6',
                'error_text' => 'Incomplete VIN; check digit fails',
                'is_valid' => false
            ],
            'additional_info' => [
                'api_response' => [
                    'source' => 'nhtsa_api',
                    'has_error' => true,
                    'error_code' => '6',
                    'error_text' => 'Incomplete VIN; check digit fails',
                    'suggested_vin' => null,
                    'additional_error_text' => 'The check digit provided does not match expected value'
                ]
            ]
        ];

        $vehicleInfo = VehicleInfo::fromArray($data);

        // Test basic info is still accessible
        $this->assertEquals('Toyota', $vehicleInfo->getMake());
        $this->assertEquals('Camry', $vehicleInfo->getModel());
        $this->assertEquals('2015', $vehicleInfo->getYear());

        // Test validation methods
        $this->assertFalse($vehicleInfo->isValid());
        $this->assertTrue($vehicleInfo->hasApiValidationError());
        $this->assertEquals('6', $vehicleInfo->getErrorCode());
        $this->assertEquals('Incomplete VIN; check digit fails', $vehicleInfo->getErrorText());

        // Test API response methods
        $apiResponse = $vehicleInfo->getApiResponse();
        $this->assertIsArray($apiResponse);
        $this->assertEquals('nhtsa_api', $apiResponse['source']);
        $this->assertTrue($apiResponse['has_error']);

        // Test additional error text
        $this->assertEquals(
            'The check digit provided does not match expected value',
            $vehicleInfo->getAdditionalErrorText()
        );
    }

    /**
     * Test that suggested VIN is accessible when provided
     */
    public function testSuggestedVinIsAccessible()
    {
        $data = [
            'make' => 'Honda',
            'validation' => [
                'error_code' => '8',
                'error_text' => 'Invalid check digit',
                'is_valid' => false
            ],
            'additional_info' => [
                'api_response' => [
                    'source' => 'nhtsa_api',
                    'has_error' => true,
                    'error_code' => '8',
                    'error_text' => 'Invalid check digit',
                    'suggested_vin' => '1HGCM82633A004352',
                    'additional_error_text' => null
                ]
            ]
        ];

        $vehicleInfo = VehicleInfo::fromArray($data);

        $this->assertEquals('1HGCM82633A004352', $vehicleInfo->getSuggestedVin());
    }

    /**
     * Test validation summary method
     */
    public function testValidationSummaryReturnsAllInfo()
    {
        $data = [
            'make' => 'Ford',
            'model' => 'F-150',
            'validation' => [
                'error_code' => '1',
                'error_text' => 'Check digit incorrect',
                'is_valid' => false
            ],
            'additional_info' => [
                'api_response' => [
                    'source' => 'nhtsa_api',
                    'has_error' => true,
                    'error_code' => '1',
                    'error_text' => 'Check digit incorrect',
                    'suggested_vin' => '1FTEW1E87JKE12345',
                    'additional_error_text' => 'Position 9 should be X'
                ]
            ]
        ];

        $vehicleInfo = VehicleInfo::fromArray($data);
        $summary = $vehicleInfo->getValidationSummary();

        $this->assertIsArray($summary);
        $this->assertFalse($summary['is_valid']);
        $this->assertTrue($summary['has_api_error']);
        $this->assertEquals('1', $summary['error_code']);
        $this->assertEquals('Check digit incorrect', $summary['error_text']);
        $this->assertEquals('Position 9 should be X', $summary['additional_error_text']);
        $this->assertEquals('1FTEW1E87JKE12345', $summary['suggested_vin']);
        $this->assertEquals('nhtsa_api', $summary['api_source']);
    }

    /**
     * Test that valid VINs have no API validation errors
     */
    public function testValidVinHasNoApiErrors()
    {
        $data = [
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => '2015',
            'validation' => [
                'error_code' => '0',
                'error_text' => null,
                'is_valid' => true
            ],
            'additional_info' => [
                'api_response' => [
                    'source' => 'nhtsa_api',
                    'has_error' => false,
                    'error_code' => '0',
                    'error_text' => null,
                    'suggested_vin' => null,
                    'additional_error_text' => null
                ]
            ]
        ];

        $vehicleInfo = VehicleInfo::fromArray($data);

        $this->assertTrue($vehicleInfo->isValid());
        $this->assertFalse($vehicleInfo->hasApiValidationError());
        $this->assertEquals('0', $vehicleInfo->getErrorCode());
        $this->assertNull($vehicleInfo->getErrorText());
        $this->assertNull($vehicleInfo->getSuggestedVin());
    }

    /**
     * Test that hasApiValidationError falls back to validation array
     */
    public function testHasApiValidationErrorFallsBackToValidation()
    {
        // When api_response is not present but validation array has error
        $data = [
            'make' => 'Chevrolet',
            'validation' => [
                'error_code' => '5',
                'error_text' => 'Some error',
                'is_valid' => false
            ],
            'additional_info' => []
        ];

        $vehicleInfo = VehicleInfo::fromArray($data);

        $this->assertTrue($vehicleInfo->hasApiValidationError());
        $this->assertNull($vehicleInfo->getApiResponse());
    }

    /**
     * Test that api_response in source_details is also accessible
     */
    public function testApiResponseFromSourceDetails()
    {
        $data = [
            'make' => 'BMW',
            'validation' => [
                'error_code' => '0',
                'error_text' => null,
                'is_valid' => true
            ],
            'additional_info' => [
                'source_details' => [
                    'nhtsa_api' => [
                        'api_response' => [
                            'source' => 'nhtsa_api',
                            'has_error' => false,
                            'error_code' => '0',
                            'error_text' => null,
                            'suggested_vin' => null,
                            'additional_error_text' => null
                        ]
                    ]
                ]
            ]
        ];

        $vehicleInfo = VehicleInfo::fromArray($data);
        $apiResponse = $vehicleInfo->getApiResponse();

        $this->assertIsArray($apiResponse);
        $this->assertEquals('nhtsa_api', $apiResponse['source']);
        $this->assertFalse($apiResponse['has_error']);
    }

    /**
     * Test toArray includes validation data
     */
    public function testToArrayIncludesValidation()
    {
        $data = [
            'make' => 'Nissan',
            'model' => 'Altima',
            'validation' => [
                'error_code' => '1',
                'error_text' => 'Test error',
                'is_valid' => false
            ]
        ];

        $vehicleInfo = VehicleInfo::fromArray($data);
        $array = $vehicleInfo->toArray();

        $this->assertArrayHasKey('validation', $array);
        $this->assertEquals('1', $array['validation']['error_code']);
        $this->assertEquals('Test error', $array['validation']['error_text']);
        $this->assertFalse($array['validation']['is_valid']);
    }
}
