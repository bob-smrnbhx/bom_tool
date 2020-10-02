<?php

namespace Home\Model;


class DateHelperModel
{
    public static function isMonday ($date)
    {
        return date("N", strtotime($date)) == 1;
    }
    
    public static function isMonthFirstDay ($date)
    {
        return date("d", strtotime($date)) == 1;
    }
    
    public static function getMondayAfterDate($dateStr, $skipWeeks = 0)
    {
        $date = new \DateTime($dateStr);
        $wd = $date->format("w");
    
        switch ($wd) {
            case 0:
                $date->add(new \DateInterval("P1D"));
                break;
            default:
                $intervalDay = 8 - $wd;
                $date->add(new \DateInterval("P{$intervalDay}D"));
        }
    
        if ($skipWeeks) {
            $date->add(new \DateInterval("P{$skipWeeks}W"));
        }
        return $date->format("Y-m-d");
    }
    
    public static function getWeekOfDate($dateStr)
    {
        $date = new \DateTime($dateStr);
        return $date->format("W");
    }
    
    public static function getFirstDayOfMonthAfterDate ($dateStr)
    {
        $m = date("m", strtotime($dateStr));
        $y = date("Y", strtotime($dateStr));
    
        if ($m == 12) {
            $y++;
            $m = 1;
        } else {
            $m++;
        }
    
        $date = "$y-$m-1";
    
        return date("Y-m-d", strtotime($date));
    }
    
    public static function getFirstMondayOfMonthAfterDate ($dateStr)
    {
        $m = date("m", strtotime($dateStr));
        $y = date("Y", strtotime($dateStr));
    
        if ($m == 12) {
            $y++;
            $m = 1;
        } else {
            $m++;
        }
    
        return self::getFirstMondayOfMonth($m, $y);
    
    }
    
    public static function getFirstMondayOfMonth ($month, $year)
    {
        if (empty($year)) {
            $year = date('Y');
        }
        $firstDay = "$year-$month-1";
        if (date("w", strtotime($firstDay)) == 1) {
            $firstMonday = $firstDay;
        } else {
            $firstMonday = self::getMondayAfterDate($firstDay);
        }
    
        return $firstMonday;
    }
}
