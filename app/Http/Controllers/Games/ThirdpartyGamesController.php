<?php

namespace App\Http\Controllers\Games;

use App\Helpers\Traits\AvailableLanguages;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;
use Laravel\Jetstream\Jetstream;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\UserBalances;
use App\Models\Currencies;
use Inertia\Inertia;
use App\Models\GamelistPublic;

class ThirdpartyGamesController extends Controller
{
    use AvailableLanguages;

    /**
     * Show the general profile settings screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function show($slug)
    {
        $selectGame = GamelistPublic::where('game_slug', $slug)->first();
        $game_header = $selectGame->game_name;
        $game_provider = $selectGame->game_provider;
        $rtp = $selectGame->rtp;
        $demo = null;
        if($selectGame->demo === 1) {
            $demo = 1;
        }

    return Inertia::render('Game', [
            'success' => true,
            'game_header' => $game_header,
            'game_slug' => $slug,
            'game_provider' => $game_provider,
            'rtp' => $rtp,
            'demo' => $demo,
    ]);
    }


    /**
     * Show the general profile settings screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public static function start($slug, $cur)
    {
        if($cur === 'demo') {
            $mode = 'demo';
        } else {
            $mode = 'real';
        }

        if($cur !== 'test') {
        $selectGame = GamelistPublic::where('game_slug', $slug)->first();
        $game_header = $selectGame->game_name;
        $game_provider = $selectGame->game_provider;
        $rtp = $selectGame->rtp;
        }
        $apikey = config('settings.main_api_key');
        $apihost = config('settings.api_server');

        $url = 'https://'.$apihost.'/v2/createSession?apikey='.$apikey.'&userid='.auth()->user()->id.'-'.auth()->user()->currentCurrency.'&mode='.$mode.'&game='.$slug.'&nick='.auth()->user()->name;

        $result = Http::retry(3, 250)->get($url);
        $iframe = $result['url'];
        if($cur === 'test') {
            return response()->json(['url' => $iframe]);
        }
        return Inertia::render('Game', [
                'success' => true,
                'game_header' => $game_header,
                'game_slug' => $slug,
                'game_provider' => $game_provider,
                'rtp' => $rtp,
                'iframe' => true,
                'iframe_url' => $iframe,
        ]);
        }



    /**
     * Show the general profile settings screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function balance(Request $request)
    {
            $selectUserBalance = UserBalances::where('user_id', $request->playerid)->where('currency_code', $request->currency)->first();
            $selectCurrency = Currencies::where('code', $request->currency)->first();
            $printUserBalance = $selectUserBalance->value;
            $usdValue = floatval($printUserBalance * $selectCurrency->usd_price);
            $centsValue = (int) number_format($usdValue, 0, '.', '') * 100;

            return response()->json([
                'status' => 'ok',
                'result' => ([
                    'balance' =>  (int) $centsValue,
                    'freegames' => (int) 0
                ]),
                'id' => 0,
                'jsonrpc' => '2.0'
            ]);
    }

    public static function balanceCheck($playerId, $currencyCode)
    {
            $selectCurrency = Currencies::where('code', $currencyCode)->first();
            $selectUserBalance = UserBalances::where('user_id', $playerId)->where('currency_code', $currencyCode)->first();
            $printUserBalance = $selectUserBalance->value;
            $usdValue = floatval($printUserBalance * $selectCurrency->usd_price);

            return (int) number_format($usdValue, 0, '.', '') * 100;
    }


    public static function newBalanceFloat($newBalanceInt, $currencyCode)
    {
            $selectCurrency = Currencies::where('code', $currencyCode)->first();
            $usdValue = floatval($newBalanceInt / $selectCurrency->usd_price);

            return floatval(number_format($usdValue, 7, '.', '') / 100);
    }


    /**
     * Show the general profile settings screen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function result(Request $request)
    {
            $bet = (int) $request->bet;
            $win = (int) $request->win;
            $currencyCode = $request->currency;

            if($bet > 0)
            {
            $balanceFirst = self::balanceCheck($request->playerid, $currencyCode);

                if($balanceFirst > $bet)
                {
                    $newBalance = ($balanceFirst - $bet);
                    $newBalanceFloat = self::newBalanceFloat($newBalance, $currencyCode);
                    $updateBetBalance = UserBalances::where('user_id', $request->playerid)->where('currency_code', $currencyCode)->update(['value' => $newBalanceFloat]);
                } else {

                    return response()->json([
                        'status' => 'notEnoughBalance',
                        'result' => ([
                            'balance' =>  (int) -1,
                            'freegames' => (int) 0
                        ]),
                        'id' => 0,
                        'jsonrpc' => '2.0'
                    ]);
                }
            }

            if($win > 0)
            {
                $balanceSecond = self::balanceCheck($request->playerid, $request->currency);
                $newBalance = ($balanceSecond + $win);
                $newBalanceFloat = self::newBalanceFloat($newBalance, $currencyCode);
                $updateWinBalance = UserBalances::where('user_id', $request->playerid)->where('currency_code', $currencyCode)->update(['value' => $newBalanceFloat]);
            }

            return response()->json([
                'status' => 'ok',
                'result' => ([
                    'balance' => self::balanceCheck($request->playerid, $currencyCode),
                    'freegames' => (int) 0
                ]),
                'id' => 0,
                'jsonrpc' => '2.0'
            ]);
        }
}
