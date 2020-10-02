<?php

class DateHelper
{
    
    static function getDateBefore($date, $interval = 1)
    {
        $interval = intval($interval);
        if ($interval < 0) {
            $interval = 0;
        }
        return date('Y-m-d', strtotime($date) - 86400 * $interval);
    }
    
    static function getDateAfter($date, $interval = 1)
    {
        $interval = intval($interval);
        if ($interval < 0) {
            $interval = 0;
        }
        return date('Y-m-d', strtotime($date) + 86400 * $interval);
    }
    
    static function getDatesBetween($startdate, $enddate)
    {
    
        $stimestamp = strtotime($startdate);
        $etimestamp = strtotime($enddate);
    
        // 计算日期段内有多少天
        $days = ($etimestamp-$stimestamp)/86400+1;
    
        $dates = array();
        for($i = 0; $i < $days; $i++){
            $dates[] = date('Y-m-d', $stimestamp + (86400 * $i));
        }
    
        return $dates;
    }
    
    static function getMondaysBetween ($startdate, $enddate)
    {
        $days = self::getDatesBetween($startdate, $enddate);
        $mondays = [];
        foreach ($days as $day) {
            if (date("N", strtotime($day)) == 1) {
                $mondays[] = $day;
            }
        }
    
        return $mondays;
    }
    
    
    static function getFirstMonthDayBetween ($startdate, $enddate)
    {
        $days = self::getDatesBetween($startdate, $enddate);
        $fmdays = [];
        foreach ($days as $day) {
            if (date("d", strtotime($day)) == 1) {
                $fmdays[] = $day;
            }
        }
    
        return $fmdays;
    }
    
    
    
    
    
    public static function isMonday ($date)
    {
        return date("N", strtotime($date)) == 1;
    }
    
    public static function isMonthFirstDay ($date)
    {
        return date("d", strtotime($date)) == 1;
    }
    
    public static function getMondayOfDate($dateStr)
    {
        $date = new \DateTime($dateStr);
        $wd = $date->format("w");
    
        switch ($wd) {
            case 0:
                $date->sub(new \DateInterval("P6D"));
                break;
            default:
                $intervalDay = $wd - 1;
                $date->sub(new \DateInterval("P{$intervalDay}D"));
        }
    
        return $date->format("Y-m-d");
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
    
    public static function getFormattedDate($date, $type = 'd')
    {
        switch ($type) {
            case 'd':
                return substr($date, 5);
            case 'w':
                return date("y年W周", strtotime($date));
            case 'm':
                return date("y年n月", strtotime($date));
            default:
                throw new \Exception("illegal date type provided: $type");
        }
    }
    
    public static function getFormattedWeekday ($date, $type='d')
    {
        switch ($type) {
            case 'd':
                $wd = date("N", strtotime($date));
                $map = [
                        1 => "周一",
                        2 => "周二",
                        3 => "周三",
                        4 => "周四",
                        5 => "周五",
                        6 => "周六",
                        7 => "周日",
                ];
                return $map[$wd];
            case 'w':
                return date("W周", strtotime($date));
            case 'm':
                return date("n月", strtotime($date));
            default:
                throw new \Exception("illegal date type provided: $type");
        }
    }
    
    
    
    public static function getActiveDatesFrom ($startDdate)
    {
        $activeDayDates = [];
        
        // 算法：从今天开始逐日递推，在遇到第3个周一前（今天如果为周一也包括在计数内）递推结束
        $mondayCount = 1;
        $date = $startDdate;
        if (self::isMonday($date)) {
            $mondayCount--;
        }
        while ($mondayCount >= 0) {
            $activeDayDates[] = $date;
    
            $date = self::getDateAfter($date);
            if (self::isMonday($date)) {
                $mondayCount--;
            }
        }
    
 
        $date = end($activeDayDates);
        while (self::isMonday($date)) {
            $date = self::getDateAfter($date);
        }
    
 

        return $activeDayDates;
    }
}