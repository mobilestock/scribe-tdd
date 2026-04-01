<?php

namespace Tests\Fixtures;

/**
 * @group Items
 */
class FakeTestController
{
    /**
     * @bodyParam name string required The item name. Example: Widget
     * @queryParam page integer The page number. Example: 1
     * @urlParam id integer required The item ID. Example: 42
     * @header X-Custom-Header string required Custom header. Example: custom-value
     * @response 200 {"id": 1, "name": "Widget"}
     * @responseField id integer The item ID.
     * @usesPagination
     */
    public function show()
    {
    }

    /**
     * @bodyParam title string required The title. Example: Test
     */
    public function store()
    {
    }

    public function index()
    {
    }
}
