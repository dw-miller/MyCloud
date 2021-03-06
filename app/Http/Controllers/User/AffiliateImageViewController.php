<?php

namespace App\Http\Controllers\User;

use App;
use App\AffiliateImageView;
use App\Media;
use App\User;
use Auth;
use Carbon\Carbon;
use GeoIP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AffiliateImageViewController extends AffiliateController
{
    public function create(Request $request, $slug, $embed)
    {
        $media = Media::where('slug', $slug)->firstOrFail();
        $media_id = $media->id;
        $owner_id = $media->user_id;
        $user = User::where('id', $owner_id)->first();
        $current_account_balance = $user->current_account_balance;
        $all_time_account_balance = $user->all_time_account_balance;
        $commissions_image = $user->commissions_image;
        $adblock = $request->input('test');
        $image_multiplier = config('image_multiplier');

        $adblock_multiplier = 1;
        if ($adblock == 1) {
            $adblock_multiplier = 0.1;
        }

        $ip = $request->ip();
        $geoIP = GeoIP::getLocation($ip);
        
        // echo $ip." ";

        $state = $geoIP->state;
        $city = $geoIP->city;
        $country = $geoIP->country;

        $country_group_1_list = config('country_group_1_list');
        $country_group_2_list = config('country_group_2_list');
        $country_group_3_list = config('country_group_3_list');

        $pos = strpos($country_group_1_list, $country);
        $pos2 = strpos($country_group_2_list, $country);
        $pos3 = strpos($country_group_3_list, $country);

        if ($pos !== false) {
            $amount_per_10000 = config('amount_for_country_group_1');
            $country_group = 1;
        } elseif ($pos2 !== false) {
            $amount_per_10000 = config('amount_for_country_group_2');
            $country_group = 2;
        } elseif ($pos3 !== false) {
            $amount_per_10000 = config('amount_for_country_group_3');
            $country_group = 3;
        } else {
            $amount_per_10000 = config('amount_for_country_group_4');
            $country_group = 4;
        }

        $amount_per_1_view = $amount_per_10000 / 10000;
        // multiply by 0.1 because this gets called every time an additional 10% is watched
        // so $commission is the value earned for each 10
        $commission = $amount_per_1_view * $image_multiplier * $adblock_multiplier;
        $new_commissions_image = $commissions_image + $commission;
        $new_current_account_balance = $current_account_balance + $commission;
        $new_all_time_account_balance = $all_time_account_balance + $commission;

        // check if owner of the media has a referrer
        // if owner was referred by anyone, credit him as well
        $referred_by = $user->referred_by;
        if ($referred_by) {
            $referrer = User::where('affiliate_id', $referred_by)->first();
            $new_current_referral_balance = $referrer->current_referral_balance + ($commission * config('referral_multiplier'));
            $new_all_time_referral_balance = $referrer->all_time_referral_balance + ($commission * config('referral_multiplier'));
            $referrer->update([
                'current_referral_balance'  => $new_current_referral_balance,
                'all_time_referral_balance' => $new_all_time_referral_balance,
            ]);
        }

        if (Auth::check()) {
            $user_id = $request->user()->id;
        } else {
            $user_id = 0;
        }

        // create or update the affiliate view/download if it already exists
        // update the account balance when creating or updating the affiliate view/download
        $affiliate = new AffiliateImageView();
        if ($affiliate->where('ip', $ip)
                ->where('media_id', $media_id)
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->count() == 0) {
            $affiliate->create([
                'media_id'          => $media_id,
                'user_id'           => $user_id,
                'owner_id'          => $owner_id,
                'country'           => $country,
                'country_group'     => $country_group,
                'state'             => $state,
                'city'              => $city,
                'ip'                => $ip,
                'adblock'           => $adblock,
                'commission'        => $commission,
                'embed'             => $embed,
            ]);

            $user->where('id', $owner_id)->update([
                'current_account_balance'  => $new_current_account_balance,
                'all_time_account_balance' => $new_all_time_account_balance,
                'commissions_image'        => $new_commissions_image,
            ]);

            return response()->json('Affiliate view counted.', 200);
        } else {
            return response()->json('Affiliate view not counted, because it is already existing. Try tomorrow again', 200);
        }

    }

    public function statistics(Request $request)
    {
        $page = 'statistics.image';
        $user = $request->user();
        $user_id = $user->id;
        $expires_at_interval = config('expires_at_interval');
        $expires_at = Carbon::now()->addMinutes($expires_at_interval);
        $prefix = 'image';

        $yesterdays_viewsFromTo_key = "$prefix.yesterdays_viewsFromTo_$user_id";
        $todays_viewsFromTo_key = "$prefix.todays_viewsFromTo_$user_id";

        if (Cache::has($yesterdays_viewsFromTo_key)) {
            $yesterdays_viewsFromTo = Cache::get($yesterdays_viewsFromTo_key);
            $todays_viewsFromTo = Cache::get($todays_viewsFromTo_key);
        } else {
            $yesterdays_viewsFromTo = [];
            $todays_viewsFromTo = [];

            for ($i = 0; $i < 12; $i++) {
                if ($i == 0) {
                    $yesterdays_viewsFromTo[$i] = AffiliateImageView::where('owner_id', $user->id)
                        ->where('created_at', '>=', Carbon::yesterday()->startOfDay())
                        ->where('created_at', '<=', Carbon::yesterday()->addHour(2))
                        ->count();

                    $todays_viewsFromTo[$i] = AffiliateImageView::where('owner_id', $user->id)
                        ->where('created_at', '>=', Carbon::today()->startOfDay())
                        ->where('created_at', '<=', Carbon::today()->addHour(2))
                        ->count();
                } else {
                    $from = $i * 2;
                    $to = $from + 2;
                    $yesterdays_viewsFromTo[$i] = AffiliateImageView::where('owner_id', $user->id)
                        ->where('created_at', '>=', Carbon::yesterday()->addHour($from))
                        ->where('created_at', '<=', Carbon::yesterday()->addHour($to))
                        ->count();

                    $todays_viewsFromTo[$i] = AffiliateImageView::where('owner_id', $user->id)
                        ->where('created_at', '>=', Carbon::today()->addHour($from))
                        ->where('created_at', '<=', Carbon::today()->addHour($to))
                        ->count();
                }
            }
            Cache::put($yesterdays_viewsFromTo_key, $yesterdays_viewsFromTo, $expires_at);
            Cache::put($todays_viewsFromTo_key, $todays_viewsFromTo, $expires_at);
        }

        $line_chart = app()->chartjs
            ->name('yesterdayVsToday')
            ->type('line')
            ->labels(
                [
                    '2:00', '4:00', '6:00', '8:00', '10:00', '12:00',
                    '14:00', '16:00', '18:00', '20:00', '22:00', '24:00',
                ]
            )
            ->datasets([
                [
                    'label'                     => "Yesterday's Views",
                    'backgroundColor'           => 'rgba(255, 99, 132, 0.31)',
                    'borderColor'               => 'rgba(255, 99, 132, 0.7)',
                    'pointBorderColor'          => 'rgba(255, 99, 132, 0.7)',
                    'pointBackgroundColor'      => 'rgba(255, 99, 132, 0.7)',
                    'pointHoverBackgroundColor' => '#fff',
                    'pointHoverBorderColor'     => 'rgba(220,220,220,1)',
                    'data'                      => [$yesterdays_viewsFromTo[0], $yesterdays_viewsFromTo[1], $yesterdays_viewsFromTo[2],
                        $yesterdays_viewsFromTo[3], $yesterdays_viewsFromTo[4], $yesterdays_viewsFromTo[5],
                        $yesterdays_viewsFromTo[6], $yesterdays_viewsFromTo[7], $yesterdays_viewsFromTo[8],
                        $yesterdays_viewsFromTo[9], $yesterdays_viewsFromTo[10], $yesterdays_viewsFromTo[11], ],
                    'fill' => false,
                ],
                [
                    'label'                     => "Today's Views",
                    'backgroundColor'           => 'rgba(54, 162, 235, 0.31)',
                    'borderColor'               => 'rgba(54, 162, 235, 0.7)',
                    'pointBorderColor'          => 'rgba(54, 162, 235, 0.7)',
                    'pointBackgroundColor'      => 'rgba(54, 162, 235, 0.7)',
                    'pointHoverBackgroundColor' => '#fff',
                    'pointHoverBorderColor'     => 'rgba(220,220,220,1)',
                    'data'                      => [$todays_viewsFromTo[0], $todays_viewsFromTo[1], $todays_viewsFromTo[2],
                        $todays_viewsFromTo[3], $todays_viewsFromTo[4], $todays_viewsFromTo[5],
                        $todays_viewsFromTo[6], $todays_viewsFromTo[7], $todays_viewsFromTo[8],
                        $todays_viewsFromTo[9], $todays_viewsFromTo[10], $todays_viewsFromTo[11], ],
                    'fill' => false,
                ],
            ])
            ->options([
                'responsive' => true,
                'legend'     => [
                    'position' => 'top',
                ],
                'title' => [
                    'display' => false,
                    'text'    => "Today's vs Yesterday's Views",
                ],
                'tooltips' => [
                    'mode'      => 'index',
                    'intersect' => false,
                ],
                'hover' => [
                    'mode'      => 'nearest',
                    'intersect' => true,
                ],
                'scales' => [
                    'xAxes' => [
                        [
                            'display'    => true,
                            'scaleLabel' => [
                                'display'     => true,
                                'labelString' => 'Hour of Day',
                            ],
                        ],
                    ],
                    'yAxes' => [
                        [
                            'display'    => true,
                            'scaleLabel' => [
                                'display'     => true,
                                'labelString' => 'Total Views',
                            ],
                            'ticks' => [
                                'beginAtZero'   => true,
                                'maxTicksLimit' => '6',
                            ],
                        ],
                    ],
                ],
            ]);

        $past_views_key = "$prefix.past_views_$user_id";
        if (Cache::has($past_views_key)) {
            $past_views = Cache::get($past_views_key);
            $todays_views = $past_views[0];
            $yesterdays_views = $past_views[1];
        } else {
            // array that holds the amount of views for each day of the past 30 days
            $past_views = [];
            for ($i = 0; $i < 30; $i++) {
                $past_views[$i] = AffiliateImageView::where('owner_id', $user->id)
                    ->where('created_at', '>=', Carbon::today()->subDays($i)->startOfDay())
                    ->where('created_at', '<=', Carbon::today()->subDays($i)->endOfDay())
                    ->count();
            }

            $todays_views = $past_views[0];
            $yesterdays_views = $past_views[1];

            Cache::put($past_views_key, $past_views, $expires_at);
        }

        $nameOfDayXDaysAgo = [];
        for ($i = 0; $i <= 6; $i++) {
            $nameOfDayXDaysAgo[$i] = Carbon::today()->subDays($i)->format('l');
        }

        $bar_chart = app()->chartjs
            ->name('barChart')
            ->type('bar')
            ->labels(['Today', $nameOfDayXDaysAgo[1], $nameOfDayXDaysAgo[2], $nameOfDayXDaysAgo[3],
                $nameOfDayXDaysAgo[4], $nameOfDayXDaysAgo[5], $nameOfDayXDaysAgo[6], ])
            ->datasets([
                [
                    'label'           => 'Views',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                    'borderWidth'     => 1,
                    'data'            => [$todays_views, $yesterdays_views, $past_views[2], $past_views[3],
                        $past_views[4], $past_views[5], $past_views[6], ],
                ],
            ])
            ->options([
                'scales' => [
                    'yAxes' => [
                        [
                            'ticks' => [
                                'beginAtZero'   => true,
                                'maxTicksLimit' => '6',
                            ],
                        ],
                    ],
                ],
            ]);

        /*
         *  Get all the statistics for the views of the last 30 days
         *  after they are cached only update the today's values
         *  or update however many days were missed.
         *  In case the last update was 30+ days ago, then get all the stats again
         *  and re-cache it forever
         */

        $views_last_30_days_key = "$prefix.views_last_30_days_$user_id";
        if (Cache::has($views_last_30_days_key)) {
            $views_last_30_days = Cache::get($views_last_30_days_key);

            $last_update = new Carbon($views_last_30_days['last_update']);
            $now = Carbon::now();
            $diff_in_days = $last_update->diffInDays($now);
            $diff_in_minutes = $last_update->diffInMinutes($now);

            // updating of the arrays
            // if there is 1 or more day difference from when the array was last updated, then shift the array
            if ($diff_in_days >= 1 && $diff_in_days < 30) {
                // shift array however many days difference there was
                for ($x = 0; $x < $diff_in_days; $x++) {
                    for ($i = 0; $i < 30; $i++) {
                        $views_last_30_days[$i + 1] = $views_last_30_days[$i];
                    }
                }

                // add today's stats to the array again to get an updated statistics
                for ($day = 0; $day < $diff_in_days; $day++) {
                    for ($i = 1; $i <= 4; $i++) {
                        $views_last_30_days[$day][$i] = AffiliateImageView::where('owner_id', $user->id)
                            ->where('country_group', $i)
                            ->where('created_at', '>=', Carbon::today()->subDays($day)->startOfDay())
                            ->where('created_at', '<=', Carbon::today()->subDays($day)->endOfDay())
                            ->count();
                    }
                }
                // this key stores the last time the array was updated
                $views_last_30_days['last_update'] = Carbon::now();
                Cache::forever($views_last_30_days_key, $views_last_30_days);

            // update the whole array
            } elseif ($diff_in_days > 29) {
                $views_last_30_days = [];
                // loop through all the 30 days
                for ($day = 0; $day < 30; $day++) {
                    // loop through all the country groups
                    for ($i = 1; $i <= 4; $i++) {
                        $views_last_30_days[$day][$i] = AffiliateImageView::where('owner_id', $user->id)
                            ->where('country_group', $i)
                            ->where('created_at', '>=', Carbon::today()->subDays($day)->startOfDay())
                            ->where('created_at', '<=', Carbon::today()->subDays($day)->endOfDay())
                            ->count();
                    }
                }
                // this key stores the last time the array was cached
                $views_last_30_days['last_update'] = Carbon::now();
                Cache::forever($views_last_30_days_key, $views_last_30_days);

                // else update today's values
            } elseif ($diff_in_minutes >= $expires_at_interval) {
                // echo "update because $diff_in_minutes >= $expires_at_interval";
                // add today's stats to the array again to get an updated array
                for ($i = 1; $i <= 4; $i++) {
                    $views_last_30_days[0][$i] = AffiliateImageView::where('owner_id', $user->id)
                        ->where('country_group', $i)
                        ->where('created_at', '>=', Carbon::today()->subDays(0)->startOfDay())
                        ->where('created_at', '<=', Carbon::today()->subDays(0)->endOfDay())
                        ->count();
                }
                // this key stores the last time the array was updated
                $views_last_30_days['last_update'] = Carbon::now();
                Cache::forever($views_last_30_days_key, $views_last_30_days);
            }
            // if no cache is present
        } else {
            $views_last_30_days = [];
            // loop through all the 30 days
            for ($day = 0; $day < 30; $day++) {
                // loop through all the country groups
                for ($i = 1; $i <= 4; $i++) {
                    $views_last_30_days[$day][$i] = AffiliateImageView::where('owner_id', $user->id)
                        ->where('country_group', $i)
                        ->where('created_at', '>=', Carbon::today()->subDays($day)->startOfDay())
                        ->where('created_at', '<=', Carbon::today()->subDays($day)->endOfDay())
                        ->count();
                }
            }
            // this key stores the last time the array was cached
            $views_last_30_days['last_update'] = Carbon::now();
            // Cache it forever because this file never gets deleted
            Cache::forever($views_last_30_days_key, $views_last_30_days);
        }

        $last_7_days_views = $past_views[6] + $past_views[5] + $past_views[4]
            + $past_views[3] + $past_views[2] + $past_views[1] + $todays_views;

        $last_30_days_views = array_sum($past_views);

        $this_years_views_key = "$prefix.this_years_views_$user_id";
        if (Cache::has($this_years_views_key)) {
            $this_years_views = Cache::get($this_years_views_key);
        } else {
            $this_years_views = AffiliateImageView::where('owner_id', $user->id)
                ->where('created_at', '>=', Carbon::parse('first day of January'))
                ->where('created_at', '<=', Carbon::parse('first day of January next year')->subSecond())
                ->count();
            Cache::put($this_years_views_key, $this_years_views, $expires_at);
        }

        /*
         *  Get all the statistics for the revenue of the last 30 days
         *  after they are cached only update the today's values
         *  or update however many days were missed.
         *  In case the last update was 30+ days ago, then get all the stats again
         *  and re-cache it forever
         */

        $this_months_revenue = [];

        $this_months_revenue_key = "$prefix.this_months_revenue_$user_id";
        if (Cache::has($this_months_revenue_key)) {
            $this_months_revenue = Cache::get($this_months_revenue_key);

            $last_update = new Carbon($this_months_revenue['last_update']);
            $now = Carbon::now();
            $diff_in_days = $last_update->diffInDays($now);
            $diff_in_minutes = $last_update->diffInMinutes($now);

            // updating of the arrays
            // if there is 1 or more day difference from when the array was last updated, then shift the array
            if ($diff_in_days >= 1 && $diff_in_days < 30) {
                // shift array however many days difference there was
                for ($x = 0; $x < $diff_in_days; $x++) {
                    for ($i = 0; $i < 30; $i++) {
                        $this_months_revenue[$i + 1] = $this_months_revenue[$i];
                    }
                }

                // echo "update because $diff_in_days since last update";
                // add however many days are different stats to the array again to get an updated statistics
                for ($i = 0; $i < $diff_in_days; $i++) {
                    $this_months_revenue[$i] = array_sum(AffiliateImageView::where('owner_id', $user->id)
                        ->where('created_at', '>=', Carbon::today()->subDays($i)->startOfDay())
                        ->where('created_at', '<=', Carbon::today()->subDays($i)->endOfDay())
                        ->pluck('commission')
                        ->toArray());
                }
                // this key stores the last time the array was updated
                $this_months_revenue['last_update'] = Carbon::now();
                Cache::forever($this_months_revenue_key, $this_months_revenue);

                // update the whole array
            } elseif ($diff_in_days > 29) {
                for ($i = 0; $i < 30; $i++) {
                    $this_months_revenue[$i] = array_sum(AffiliateImageView::where('owner_id', $user->id)
                        ->where('created_at', '>=', Carbon::today()->subDays($i)->startOfDay())
                        ->where('created_at', '<=', Carbon::today()->subDays($i)->endOfDay())
                        ->pluck('commission')
                        ->toArray());
                }

                $this_months_revenue['last_update'] = Carbon::now();
                Cache::forever($this_months_revenue_key, $this_months_revenue, $expires_at);

                // else update today's values if the last time updated is more than $expires_at_interval minutes
            } elseif ($diff_in_minutes >= $expires_at_interval) {
                // echo "update because $diff_in_minutes >= $expires_at_interval";
                // add today's stats to the array again to get an updated array
                $this_months_revenue[0] = array_sum(AffiliateImageView::where('owner_id', $user->id)
                    ->where('created_at', '>=', Carbon::today()->subDays(0)->startOfDay())
                    ->where('created_at', '<=', Carbon::today()->subDays(0)->endOfDay())
                    ->pluck('commission')
                    ->toArray());

                // this key stores the last time the array was updated
                $this_months_revenue['last_update'] = Carbon::now();
                Cache::forever($this_months_revenue_key, $this_months_revenue);
            }
            // echo "no update because diff_in_days $diff_in_days and diffinminutes $diff_in_minutes";
        } else {
            // echo "no cache is present";
            // If no cache is present
            // Revenue today, the past 7 days, and 30 days stored in one array
            for ($i = 0; $i < 30; $i++) {
                $this_months_revenue[$i] = array_sum(AffiliateImageView::where('owner_id', $user->id)
                    ->where('created_at', '>=', Carbon::today()->subDays($i)->startOfDay())
                    ->where('created_at', '<=', Carbon::today()->subDays($i)->endOfDay())
                    ->pluck('commission')
                    ->toArray());
            }
            $this_months_revenue['last_update'] = Carbon::now();
            Cache::forever($this_months_revenue_key, $this_months_revenue, $expires_at);
        }

        $todays_revenue = $this->trimTrailingZeroes($this_months_revenue[0]);
        $yesterdays_revenue = $this->trimTrailingZeroes($this_months_revenue[1]);
        $last_7_days_revenue = $this->trimTrailingZeroes($todays_revenue + $yesterdays_revenue + $this_months_revenue[2]
            + $this_months_revenue[3] + $this_months_revenue[4] + $this_months_revenue[5] + $this_months_revenue[6]);
        $last_30_days_revenue = $this->trimTrailingZeroes(array_sum($this_months_revenue));
        $this_years_image_revenue = $this->trimTrailingZeroes($user->commissions_image);

        $revenue_chart = app()->chartjs
            ->name('revenueChart')
            ->type('bar')
            ->labels(['Today', $nameOfDayXDaysAgo[1], $nameOfDayXDaysAgo[2], $nameOfDayXDaysAgo[3],
                $nameOfDayXDaysAgo[4], $nameOfDayXDaysAgo[5], $nameOfDayXDaysAgo[6], ])
            ->datasets([
                [
                    'label'           => 'Revenue',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.7)',
                    'borderWidth'     => 1,
                    'data'            => [$todays_revenue, $yesterdays_revenue, $this_months_revenue[2],
                        $this_months_revenue[3], $this_months_revenue[4],
                        $this_months_revenue[5], $this_months_revenue[6], ],
                ],
            ])
            ->options([
                'scales' => [
                    'yAxes' => [
                        [
                            'ticks' => [
                                'beginAtZero'   => true,
                                'maxTicksLimit' => '6',
                            ],
                        ],
                    ],
                ],
            ]);

        $adblock_usage_yes_key = "$prefix.adblock_usage_yes_$user_id";
        $adblock_usage_no_key = "$prefix.adblock_usage_no_$user_id";
        if (Cache::has($adblock_usage_yes_key)) {
            $adblock_usage_yes = Cache::get($adblock_usage_yes_key);
            $adblock_usage_no = Cache::get($adblock_usage_no_key);
        } else {
            $adblock_usage_yes = AffiliateImageView::where('owner_id', $user->id)
                ->where('adblock', 1)
                ->count();

            $adblock_usage_no = AffiliateImageView::where('owner_id', $user->id)
                ->where('adblock', 0)
                ->count();

            Cache::put($adblock_usage_yes_key, $adblock_usage_yes, $expires_at);
            Cache::put($adblock_usage_no_key, $adblock_usage_no, $expires_at);
        }

        // suppress errors because of division by 0
        $adblock_percentage = @round($adblock_usage_yes * 100 / ($adblock_usage_yes + $adblock_usage_no), 2);
        if (is_nan($adblock_percentage)) {
            $adblock_percentage = 0;
        }

        // refactor this eventually before 2018
        $account_balance = $this->trimTrailingZeroes($user->account_balance);

        return view(
            'affiliate.statistics.image',
            compact(
                'page',
                'user',
                'line_chart',
                'bar_chart',
                'revenue_chart',
                'todays_views',
                'yesterdays_views',
                'last_7_days_views',
                'last_30_days_views',
                'this_years_views',
                'month_chart',
                'this_years_image_revenue',
                'views_last_30_days',
                'expires_at_interval',
                'this_months_revenue',
                'todays_revenue',
                'yesterdays_revenue',
                'last_7_days_revenue',
                'last_30_days_revenue',
                'adblock_usage_no',
                'adblock_usage_yes',
                'adblock_percentage'
            )
        );
    }

    public function trimTrailingZeroes($str)
    {
        return preg_replace('/(\.[0-9]+?)0*$/', '$1', number_format($str, 10));
    }
}
