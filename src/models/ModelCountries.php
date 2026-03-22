<?php

class ModelCountries
{
    public static function GetCountries()
    {
        $countries = json_decode(
            file_get_contents(__DIR__ . "/../../data/countries.json"),
            true
        );
        return $countries;
    }
}