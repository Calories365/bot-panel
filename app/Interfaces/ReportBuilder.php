<?php

namespace App\Interfaces;

use App\Models\Bot;
use DateTime;

interface ReportBuilder
{
    public function setBot(Bot $bot);

    public function setDateRange(DateTime $startDate, DateTime $endDate);

    public function includeNewUsers();

    public function includeBannedUsers();

    public function includePremiumUsers();

    public function includeDefaultUsers();

    public function build(): array;
}
