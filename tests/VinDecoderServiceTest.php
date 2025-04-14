<?php

namespace Shekel\VinPackage\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shekel\VinPackage\Services\VinDecoderService;
use ReflectionClass;

class VinDecoderServiceTest extends TestCase
{
    /**
     * Test decoding a VIN with mock API response
     */
    public function testDecode()
    {
        // Mock API response
        $mockResponse = [
            'Count' => 1,
            'Message' => 'Results returned successfully',
            'Results' => [
                [
                    'Variable' => 'Make',
                    'Value' => 'HONDA'
                ],
                [
                    'Variable' => 'Model',
                    'Value' => 'CIVIC'
                ],
                [
                    'Variable' => 'Model Year',
                    'Value' => '2015'
                ],
                [
                    'Variable' => 'Trim',
                    'Value' => 'LX'
                ],
                [
                    'Variable' => 'Engine',
                    'Value' => '2.0L I4'
                ],
                [
                    'Variable' => 'Plant City',
                    'Value' => 'GREENSBURG'
                ],
                [
                    'Variable' => 'Body Class',
                    'Value' => 'Sedan'
                ]
            ]
        ];

        // Create mock handler
        $mock = new MockHandler([
            new Response(200, [], json_encode($mockResponse))
        ]);
        
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        
        // Use reflection to set the client property on the service
        $decoderService = new VinDecoderService();
        $reflection = new ReflectionClass($decoderService);
        
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($decoderService, $client);
        
        // Test the decoder
        $result = $decoderService->decode('1HGCM82633A004352');
        
        // Verify the result using the VehicleInfo getters
        $this->assertEquals('HONDA', $result->getMake());
        $this->assertEquals('CIVIC', $result->getModel());
        $this->assertEquals('2015', $result->getYear());
        $this->assertEquals('LX', $result->getTrim());
        $this->assertEquals('2.0L I4', $result->getEngine());
    }
    
    /**
     * Test model year decoder
     */
    public function testDecodeModelYear()
    {
        $decoderService = new VinDecoderService();
        
        // Test a few year codes
        $this->assertEquals('2010', $decoderService->decodeModelYear('A'));
        $this->assertEquals('2018', $decoderService->decodeModelYear('J'));
        $this->assertEquals('2023', $decoderService->decodeModelYear('P'));
        $this->assertEquals('2005', $decoderService->decodeModelYear('5'));
        
        // Test unknown code
        $this->assertEquals('Unknown', $decoderService->decodeModelYear('Q'));
    }
}