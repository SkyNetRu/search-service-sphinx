<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Drivers\Mysqli\Connection;

class Sphinx extends Controller
{
    public function search(SearchRequest $request)
    {
        $conn = new Connection();
        $conn->setParams([
            'host' => config('database.connections.sphinx.host'),
            'port' => config('database.connections.sphinx.port')
        ]);

        $query = (new SphinxQL($conn))->select('*')
            ->from(config('database.connections.sphinx.database'))
            ->match(['title', 'title_rus', 'introtext', 'introtext_rus'], $request->search_string)
            ->where('sold', '=', 0)
            ->where('published', '=', 1);

        $result = $query->execute();
        $result = collect($result->fetchAllAssoc())->pluck('id');

        $items = Item::whereIn('id', $result)->get()->pluck('id');

        return response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => $items
        ]);
    }
}
