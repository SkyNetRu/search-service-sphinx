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

        $aliasesIds = $this->aliasWords($request->search_string, $request->catid);

        $likeItems = Item::where('title', 'like', '%'.$request->search_string.'%')->where('catid', $request->catid)
            ->get()->pluck('id')->toArray();

        $searchWordsIds = $this->getSearchWordsIds($request->search_string, $request->catid);

        $alterOptionsId = $this->alterOptionsSearch($request->search_string, $request->catid);

        $itemsIds = array_merge($itemsIds, $aliasesIds, $likeItems, $searchWordsIds, $alterOptionsId);

        $log = new SearchLog();
        $log->search_string = $request->search_string;
        $log->save();

        return response()->json([
            'success' => true,
            'data' => [
                'items_ids' => $itemsIds,
                'alterOptionsId' => $alterOptionsId,
                'likeItems' => $likeItems,
                'searchWordsIds' => $searchWordsIds,
                'aliasesIds' => $aliasesIds,
            ]
        ]);
    }

    public function aliasWords (string $search_string, $catid = 0)
    {
        $search_string = str_replace('.', '', strtolower(trim($search_string)));
        $aliasWordsArr = $aliasesIds = [];

        foreach( config('word_alias.aliases') as $aliasWords ){
            foreach( $aliasWords as $aliasWord ){
                if ($search_string == $aliasWord) {
                    $aliasWordsArr = $aliasWords;
                    break 2;
                }
            }
        }

        if (count($aliasWordsArr)) {
            $query = Item::where(function ($subQuery) use ($catid, $aliasWordsArr) {
                $subQuery->where('catid', $catid)
                    ->where('title', 'like', '%' . $aliasWordsArr[0] . '%');
            });

            for ($i = 1; $i < count($aliasWords); $i++) {
                $query->orWhere(function ($subQuery) use ($catid, $aliasWordsArr) {
                    $subQuery->where('catid', $catid)
                        ->where('title', 'like', '%' . $aliasWordsArr[0] . '%');
                });
            }

            $aliasesIds = $query->get()->pluck('id')->toArray();
        }

        return $aliasesIds;
    }

    public function alterOptionsSearch ($search_string, $catid = 0)
    {
        $search_words = explode(' ', str_replace('.', '', strtolower(trim($search_string))));


        $spninxStringOptions = [];
        $previousAliases = [];
        foreach($search_words as $wkey =>  $word) {
            $matchAliases = array_filter(config('word_alias.aliases'), function($alias) use ($word) {
                return in_array($word, $alias);
            });

            $matchAliases = count($matchAliases) ? array_values($matchAliases)[0] : [$word];

            foreach($matchAliases as $matchAlias) {
                if (count($previousAliases)) {
                    foreach($previousAliases as $previousAlias){
                        $search_words_clone = $search_words;
                        $search_words_clone[$wkey - 1] = $previousAlias;
                        $search_words_clone[$wkey] = $matchAlias;
                        $spninxStringOptions[] = implode(' ', $search_words_clone);
                    }
                } else {
                    $search_words_clone = $search_words;
                    $search_words_clone[$wkey] = $matchAlias;
                    $spninxStringOptions[] = implode(' ', $search_words_clone);
                }
            }

            $previousAliases = $matchAliases;
        }

        $conn = new Connection();
        $conn->setParams([
            'host' => config('database.connections.sphinx.host'),
            'port' => config('database.connections.sphinx.port')
        ]);

        $query = (new SphinxQL($conn))->select('*')
            ->from(config('database.connections.sphinx.database'))
            ->where('published', 1);

        foreach ($spninxStringOptions as $option) {
            $query->match(['title', 'title_rus', 'introtext', 'introtext_rus'], $option);
        }

        $result = $query->execute();
        $result = collect($result->fetchAllAssoc())->pluck('id');

        $alterOptionsIds = Item::whereIn('id', $result)->where('catid', $catid)->get(['id']);
        $alterOptionsIds = $alterOptionsIds->pluck('id')->toArray();

        return $alterOptionsIds;
    }

    public function getSearchWordsIds($search_string, $catid = 0) {
        $search_words = explode(' ', str_replace('.', '', strtolower(trim($search_string))));
        $searchWordsSql = implode('%', $search_words);
        return Item::where(function ($subQuery) use ($catid, $searchWordsSql) {
            $subQuery->where('catid', $catid)
                ->where('title', 'like', '%' . $searchWordsSql . '%');
        })->get()->pluck('id')->toArray();
    }
}

