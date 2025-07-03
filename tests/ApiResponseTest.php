<?php

use Dataprovider\SDK\Response\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    public function testApiResponseGetJsonBodyAssociative()
    {
        $json = json_encode(['hello' => 'world']);
        $res = new ApiResponse($json, 200);

        $this->assertEquals($json, $res->getBody());
        $this->assertEquals(['hello' => 'world'], $res->getJsonBody());
        $this->assertEquals(200, $res->getStatusCode());
    }

    public function testApiResponseGetJsonBodyNonAssociative()
    {
        $json = json_encode(['hello' => 'world']);
        $res = new ApiResponse($json, 200);

        $cls = new \stdClass();
        $cls->hello = 'world';

        $this->assertEquals($json, $res->getBody());
        $this->assertEquals($cls, $res->getJsonBody(false));
        $this->assertEquals(200, $res->getStatusCode());
    }
}
