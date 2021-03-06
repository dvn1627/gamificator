<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Validator;
use Carbon\Carbon;

use App\Models\{PromoutedStreamer, Streamer, User, MainStreamer};

class MainStreamersManagementController extends Controller
{
        /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['show']]);
        header("Access-Control-Allow-Origin: " . getOrigin($_SERVER));
    }


    public function show(Request $request)
    {
        $now = Carbon::createFromTimestampUTC(0);
        $mainStreamer = MainStreamer::where([
            ['promouted_start', '>=', $now],
            ['promouted_end', '<=', $now],
        ])->first();
        if ($mainStreamer) {
            $promouted = $mainStreamer->promouted()->first();
            $streamer = $promouted->streamer()->first();
            return response()->json([
                'data' => $streamer->name,
            ]);
        }
        $allStreamers = Streamer::all();
        $num = round(rand(count($allStreamers)));
        return response()->json([
            'data' => $allStreamers[$num]->name,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),
        [
            'promouted_id'      => 'required|numeric',
            'promouted_start'   => 'required|string|min:8|max:8',
            'promouted_end'     => 'required|string|min:8|max:8',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ]);
        }
        if (!PromoutedStreamer::find($request->promouted_id)) {
            return response()->json([
                'errors' => ['promouted streamer id not found'],
            ]);
        }
        if ($this->compareTime($request->promouted_start, '>', $request->promouted_end)){
            return response()->json([
                'errors' => ['ending time must be later than starting time '],
            ]);
        }
        $allMainStreamers = MainStreamer::all();
        foreach ($allMainStreamers as $allMainStreamer) {
            if ($this->compareTime($request->promouted_end, '>', $allMainStreamer->promouted_start) 
                && $this->compareTime($request->promouted_start, '<', $allMainStreamer->promouted_end)) {
                return response()->json([
                    'errors' => ['check time. other streams shows at this time'],
                ]); 
            }
        }
        $newMainStream = new MainStreamer();
        $newMainStream->promouted_streamer_id = $request->promouted_id;
        $newMainStream->promouted_start = $request->promouted_start;
        $newMainStream->promouted_end = $request->promouted_end;
        $newMainStream->save();
        return response()->json([
            'message' => ['successful added streamer to promouted streamers'],
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(),
        [
            'id'                => 'required|numeric',
            'promouted_start'   => 'required|string|min:8|max:8',
            'promouted_end'     => 'required|string|min:8|max:8',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ]);
        }
        \Log::info("updating ID={$request->id}");
        \Log::info("updating start={$request->promouted_start}");
        \Log::info("updating end={$request->promouted_end}");
        $mainStreamer = MainStreamer::find($request->id);
        if (!$mainStreamer) {
            return response()->json([
                'errors' => ['main streamer id not found'],
            ]);
        }
        if ($this->compareTime($request->promouted_start, '>', $request->promouted_end)){
            return response()->json([
                'errors' => ['ending time must be later than starting time '],
            ]);
        }
        $allMainStreamers = MainStreamer::all();
        foreach ($allMainStreamers as $allMainStreamer) {
            if ($this->compareTime($request->promouted_end, '>', $allMainStreamer->promouted_start) 
                && $this->compareTime($request->promouted_start, '<', $allMainStreamer->promouted_end)) {
                return response()->json([
                    'errors' => ['check time. other streams shows at this time'],
                ]); 
            }
        }
        // $mainStreamer->promouted_streamer_id = $request->promouted_id;
        $mainStreamer->promouted_start = $request->promouted_start;
        $mainStreamer->promouted_end = $request->promouted_end;
        $mainStreamer->save();
        return response()->json([
            'message' => ['successful updated main streamer'],
        ]);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(),
        [
            'id'   => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ]);
        }
        $mainStreamer = MainStreamer::find($request->id);
        if (!$mainStreamer) {
            return response()->json([
                'errors' => ['this streamer not in main list' . $request->id],
            ]);
        }
        $mainStreamer->delete();
        return response()->json([
            'message' => ['successful deleted streamer from main streamers'],
        ]);
    }

    public function list(Request $request)
    {
        $mainStreamers = MainStreamer::orderBy('promouted_start', 'asc')->get();
        for ($i = 0; $i < count($mainStreamers); $i++) {
            $promouted = $mainStreamers[$i]->promouted()->first();
            $streamer = $promouted->streamer()->first();
            $mainStreamers[$i]->name = $streamer->name;
            $mainStreamers[$i]->duration = $this->duration($mainStreamers[$i]->promouted_start, $mainStreamers[$i]->promouted_end);
        }
        return response()->json([
            'data' => [
                'main_streamers' => $mainStreamers,
            ],
        ]);
    }

    private function compareTime($timeA, $symbol, $timeB) {
        $timestampA = $this->timeToStamp($timeA);
        $timestampB = $this->timeToStamp($timeB);
        switch ($symbol) {
            case '=':
                if (substr($timeA, 0, 5) === substr($timeB, 0, 5)) {
                    return true;
                }
                return false;
                break;
            case '>':
                if ($timestampA > $timestampB) {
                    return true;
                }
                return false;
                break;
            case '>=':
                if ($timestampA >= $timestampB) {
                    return true;
                }
                return false;
                break;
            case '<':
                if ($timestampA < $timestampB) {
                    return true;
                }
                return false;
                break;
            case '<=':
                if ($timestampA <= $timestampB) {
                    return true;
                }
                return false;
                break;
        }
        throw new \Exception('compareTimeError');
    }

    private function timeStrToObj($timeStr) {
        $obj = new stdClass();
        $obj->HH  =   (int) substr($timeStr, 0 , 2);
        $obj->mm  =   (int) substr($timeStr, 3 , 2);
        $obj->timestamp = $obj->HH * 60 + $obj->mm;
    }

    private function timeToStamp($timeStr) {
        $HH  =   (int) substr($timeStr, 0 , 2);
        $mm  =   (int) substr($timeStr, 3 , 2);
        return $HH * 60 + $mm;
    }

    private function duration($timeStart, $timeEnd) {
        return $this->timeToStamp($timeEnd) - $this->timeToStamp($timeStart);
    }

}
