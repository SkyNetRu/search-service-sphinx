<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Models\Item;
use App\Models\SearchLog;
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
            ->where('published', 1);

        $result = $query->execute();
        $result = collect($result->fetchAllAssoc())->pluck('id');

        $itemsIds = Item::whereIn('id', $result)->where('catid', $request->catid)->get(['id']);
        $itemsIds = $itemsIds->pluck('id')->toArray();
        $aliasesIds = [];

        $aliasWords = $this->aliasWords($request->search_string);
        if (count($aliasWords)) {
            $query = Item::where(function (Builder $subQuery) use ($request, $aliasWords) {
                $subQuery->where('catid', $request->catid)
                    ->where('title', 'like', '%' . $aliasWords[0] . '%');
            });

            for ($i = 1; $i < count($aliasWords); $i++) {
                $query->orWhere(function (Builder $subQuery) use ($request, $aliasWords) {
                    $subQuery->where('catid', $request->catid)
                        ->where('title', 'like', '%' . $aliasWords[0] . '%');
                });
            }

            $aliasesIds = $query->get()->pluck('id')->toArray();
        }

        $itemsIds = array_merge($itemsIds, $aliasesIds);

        $log = new SearchLog();
        $log->search_string = $request->search_string;
        $log->save();

        return response()->json([
            'success' => true,
            'data' => [
                'items_ids' => $itemsIds
            ]
        ]);
    }

    public function aliasWords (string $serach_string)
    {
        foreach( config('word_alias.aliases') as $aliasWords ){
            foreach( $aliasWords as $aliasWord ){
                if ($serach_string == $aliasWord) {
                    return $aliasWords;
                }
            }
        }

        return [];
    }
}
