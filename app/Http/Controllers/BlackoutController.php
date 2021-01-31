<?php

namespace App\Http\Controllers;

use App\Models\AcademicDate;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Blackout;
use Illuminate\Validation\ValidationException;

class BlackoutController extends Controller
{

  /**
   * Store a newly created resource in storage.
   *
   * @param Request $request
   * @param Room $room
   * @return RedirectResponse
   * @throws ValidationException
   */
  public function store(Request $request, Room $room)
  {
    $request->validateWithBag('createBlackout', [
      'start' => ['required', 'date'],
      'end' => ['required', 'date', "after:start"],
      'name' => ['required', 'string']
    ]);

    $academicDate = AcademicDate::query()
      ->where('start_date', '<', Carbon::now())
      ->where('end_date', '>', Carbon::now())
      ->first();

    switch ($request->get('recurring')) {
      case'daily':
        $startDatetime = Carbon::parse($request->start);
        $endDatetime = Carbon::parse($request->end);

        if (!$startDatetime->isSameDay($endDatetime)) {
          throw ValidationException::withMessages(['recurrence_error' => 'Start date and End date cannot be different for recurring daily blackout']);
        }

        $beginLoop = new DateTime(Carbon::now()->format("Y-m-d"));
        $endLoop = new DateTime($academicDate->end_date);

        for ($i = $beginLoop; $i <= $endLoop; $i->modify('+1 day')) {
          Blackout::create([
            'start_time' => $i->format("Y-m-d") . $startDatetime->toTimeString(),
            'end_time' => $i->format("Y-m-d") . $endDatetime->toTimeString(),
            'name' => $request->name
          ])->rooms()->attach($room);
        }
        break;
      case 'weekly':
        $startDatetime = Carbon::parse($request->start);
        $endDatetime = Carbon::parse($request->end);

        if (!$startDatetime->isSameWeek($endDatetime)) {
          throw ValidationException::withMessages(['recurrence_error' => 'Start date and End date cannot be from different weeks for recurring daily blackout']);
        }

        $diffInWeeks = $startDatetime->diffInWeeks(Carbon::parse($academicDate->end_date));

        for ($i = 0; $i < $diffInWeeks; $i++) {
          Blackout::create([
            'start_time' => $startDatetime->format("Y-m-d") . $startDatetime->toTimeString(),
            'end_time' => $endDatetime->format("Y-m-d") . $endDatetime->toTimeString(),
            'name' => $request->name
          ])->rooms()->attach($room);
          $startDatetime->addWeek();
          $endDatetime->addWeek();
        }
        break;
      default:
        Blackout::create([
          'start_time' => $request->start,
          'end_time' => $request->end,
          'name' => $request->name
        ])->rooms()->attach($room);
        break;
    }

    return back();
  }

  /**
   * Update the specified resource in storage.
   *
   * @param Request $request
   * @param Room $room
   * @param Blackout $blackout
   * @return RedirectResponse
   * @noinspection PhpUnusedParameterInspection
   */
  public function update(Request $request, Room $room, Blackout $blackout)
  {
    $request->validateWithBag('createBlackout', [
      'start' => ['required', 'date'],
      'end' => ['required', 'date', "after:start"],
    ]);

    if ($request->start) {
      $blackout->start_time = $request->start;
    }

    if ($request->end) {
      $blackout->end_time = $request->end;
    }

    $blackout->save();
    return back()->with('flash', ['updated']);
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param Room $room
   * @param Blackout $blackout
   * @return RedirectResponse
   * @throws Exception
   * @noinspection PhpUnusedParameterInspection
   */
  public function destroy(Room $room, Blackout $blackout)
  {
    $blackout->rooms()->sync([]);
    $blackout->delete();
    return back()->with('flash', ['deleted']);
  }

  public function index(Room $room)
  {
    return inertia('Admin/Blackouts/Room', [
      "blackouts" => $room->blackouts()->get(),
      "room" => $room]);
  }

}
