<?php

namespace Shekel\VinPackage\Tests\Services;

use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Services\VinDataMerger;
use Shekel\VinPackage\ValueObjects\VinDataSourceResult;

class VinDataMergerTest extends TestCase
{
    private VinDataMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new VinDataMerger();
    }

    public function testMergeByPriority()
    {
        $localResult = new VinDataSourceResult(
            true,
            [
                'make' => 'Local Make',
                'model' => 'Local Model',
                'manufacturer' => 'Local Manufacturer'
            ],
            'local'
        );

        $nhtsaResult = new VinDataSourceResult(
            true,
            [
                'make' => 'NHTSA Make',
                'model' => 'NHTSA Model',
                'year' => '2020',
                'validation' => ['is_valid' => true]
            ],
            'nhtsa_api'
        );

        $clearvinResult = new VinDataSourceResult(
            true,
            [
                'make' => 'ClearVIN Make',
                'trim' => 'XLE Premium',
                'dimensions' => ['length' => '200 in']
            ],
            'clearvin'
        );

        $results = [$localResult, $nhtsaResult, $clearvinResult];
        $merged = $this->merger->merge($results);

        // NHTSA should take priority for make/model over local
        $this->assertEquals('NHTSA Make', $merged['make']);
        $this->assertEquals('NHTSA Model', $merged['model']);

        // NHTSA exclusive fields
        $this->assertEquals('2020', $merged['year']);
        $this->assertEquals(['is_valid' => true], $merged['validation']);

        // ClearVIN exclusive fields
        $this->assertEquals('XLE Premium', $merged['trim']);
        $this->assertEquals(['length' => '200 in'], $merged['dimensions']);

        // Local fallback field
        $this->assertEquals('Local Manufacturer', $merged['manufacturer']);
    }

    public function testMergeBestEffort()
    {
        $this->merger->setMergeStrategy('best_effort');

        $partialResult1 = new VinDataSourceResult(
            true,
            ['make' => 'Toyota', 'model' => null],
            'source1'
        );

        $partialResult2 = new VinDataSourceResult(
            true,
            ['model' => 'Camry', 'year' => '2021'],
            'source2'
        );

        $results = [$partialResult1, $partialResult2];
        $merged = $this->merger->merge($results);

        $this->assertEquals('Toyota', $merged['make']);
        $this->assertEquals('Camry', $merged['model']);
        $this->assertEquals('2021', $merged['year']);
    }

    public function testMergeComplete()
    {
        $this->merger->setMergeStrategy('complete');

        $incompleteResult = new VinDataSourceResult(
            true,
            ['make' => 'Toyota'],
            'incomplete_source'
        );

        $completeResult = new VinDataSourceResult(
            true,
            [
                'make' => 'Honda',
                'model' => 'Civic',
                'year' => '2022',
                'trim' => 'Sport'
            ],
            'complete_source'
        );

        $results = [$incompleteResult, $completeResult];
        $merged = $this->merger->merge($results);

        // Complete strategy should prefer more complete data sets
        $this->assertEquals('Honda', $merged['make']);
        $this->assertEquals('Civic', $merged['model']);
        $this->assertEquals('2022', $merged['year']);
        $this->assertEquals('Sport', $merged['trim']);
    }

    public function testSetFieldPriority()
    {
        $this->merger->setFieldPriority('trim', ['clearvin', 'nhtsa_api', 'local']);

        $localResult = new VinDataSourceResult(
            true,
            ['trim' => 'Base'],
            'local'
        );

        $nhtsaResult = new VinDataSourceResult(
            true,
            ['trim' => 'Standard'],
            'nhtsa_api'
        );

        $clearvinResult = new VinDataSourceResult(
            true,
            ['trim' => 'Premium'],
            'clearvin'
        );

        $results = [$localResult, $nhtsaResult, $clearvinResult];
        $merged = $this->merger->merge($results);

        // ClearVIN should win for trim field due to custom priority
        $this->assertEquals('Premium', $merged['trim']);
    }

    public function testOverrideEmptyFields()
    {
        $resultWithEmpty = new VinDataSourceResult(
            true,
            [
                'make' => 'Toyota',
                'model' => '',
                'year' => null,
                'trim' => '   '
            ],
            'source1'
        );

        $resultWithValues = new VinDataSourceResult(
            true,
            [
                'model' => 'Camry',
                'year' => '2023',
                'trim' => 'LE'
            ],
            'source2'
        );

        $results = [$resultWithEmpty, $resultWithValues];
        $merged = $this->merger->merge($results);

        $this->assertEquals('Toyota', $merged['make']);
        $this->assertEquals('Camry', $merged['model']); // Override empty string
        $this->assertEquals('2023', $merged['year']); // Override null
        $this->assertEquals('LE', $merged['trim']); // Override whitespace
    }

    public function testPreserveValidation()
    {
        $localResult = new VinDataSourceResult(
            true,
            [
                'make' => 'Toyota',
                'validation' => ['error' => 'Local validation error']
            ],
            'local'
        );

        $nhtsaResult = new VinDataSourceResult(
            true,
            [
                'make' => 'Honda',
                'validation' => [
                    'is_valid' => true,
                    'error_code' => '0',
                    'error_text' => null
                ]
            ],
            'nhtsa_api'
        );

        $results = [$localResult, $nhtsaResult];
        $merged = $this->merger->merge($results);

        // NHTSA validation should always be preserved
        $this->assertEquals([
            'is_valid' => true,
            'error_code' => '0',
            'error_text' => null
        ], $merged['validation']);
    }

    public function testMergeAdditionalInfo()
    {
        $result1 = new VinDataSourceResult(
            true,
            [
                'make' => 'Toyota',
                'additional_info' => [
                    'WMI' => '5TD',
                    'source1_field' => 'value1'
                ]
            ],
            'source1'
        );

        $result2 = new VinDataSourceResult(
            true,
            [
                'model' => 'Camry',
                'additional_info' => [
                    'VDS' => 'YK3DC8',
                    'source2_field' => 'value2'
                ]
            ],
            'source2'
        );

        $results = [$result1, $result2];
        $merged = $this->merger->merge($results);

        $expectedAdditionalInfo = [
            'WMI' => '5TD',
            'source1_field' => 'value1',
            'VDS' => 'YK3DC8',
            'source2_field' => 'value2'
        ];

        $this->assertEquals($expectedAdditionalInfo, $merged['additional_info']);
    }

    public function testConflictResolution()
    {
        $this->merger->setConflictResolution('newest');

        $olderResult = new VinDataSourceResult(
            true,
            ['make' => 'Toyota'],
            'older_source',
            null,
            ['timestamp' => 1640995200] // 2022-01-01
        );

        $newerResult = new VinDataSourceResult(
            true,
            ['make' => 'Honda'],
            'newer_source',
            null,
            ['timestamp' => 1672531200] // 2023-01-01
        );

        $results = [$olderResult, $newerResult];
        $merged = $this->merger->merge($results);

        $this->assertEquals('Honda', $merged['make']);
    }

    public function testMergeMetadata()
    {
        $result1 = new VinDataSourceResult(
            true,
            ['make' => 'Toyota'],
            'source1',
            null,
            ['execution_time' => 0.5, 'cache_hit' => true]
        );

        $result2 = new VinDataSourceResult(
            true,
            ['model' => 'Camry'],
            'source2',
            null,
            ['execution_time' => 1.2, 'api_version' => 'v2']
        );

        $results = [$result1, $result2];
        $merged = $this->merger->merge($results);

        $expectedMetadata = [
            'sources' => ['source1', 'source2'],
            'total_execution_time' => 1.7,
            'source_details' => [
                'source1' => ['execution_time' => 0.5, 'cache_hit' => true],
                'source2' => ['execution_time' => 1.2, 'api_version' => 'v2']
            ]
        ];

        $this->assertEquals($expectedMetadata, $merged['cache_metadata']);
    }

    public function testEmptyResults()
    {
        $merged = $this->merger->merge([]);

        $this->assertEquals([], $merged);
    }

    public function testSingleResult()
    {
        $result = new VinDataSourceResult(
            true,
            ['make' => 'Toyota', 'model' => 'Camry'],
            'single_source'
        );

        $merged = $this->merger->merge([$result]);

        $this->assertEquals('Toyota', $merged['make']);
        $this->assertEquals('Camry', $merged['model']);
    }

    public function testFailedResults()
    {
        $successResult = new VinDataSourceResult(
            true,
            ['make' => 'Toyota'],
            'success_source'
        );

        $failedResult = new VinDataSourceResult(
            false,
            [],
            'failed_source',
            'Connection error'
        );

        $results = [$failedResult, $successResult];
        $merged = $this->merger->merge($results);

        $this->assertEquals('Toyota', $merged['make']);
        // Failed results should not contribute to merged data
        $this->assertArrayNotHasKey('error', $merged);
    }
}
