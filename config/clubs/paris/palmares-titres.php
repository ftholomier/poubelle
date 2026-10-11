<?php
// Palmarès de référence du club (Wikipédia, vérifié le 11 octobre 2026) : titre, finale (finaliste ou vice-champion), demi.
// Trophée des champions : année de l'édition (celles de 2020, 2023, 2024 et 2025 se sont jouées en janvier suivant).
$out = [];
$add = function (string $comp, string $kind, array $years) use (&$out): void {
    foreach ($years as $y) {
        $out[] = ['comp' => $comp, 'year' => $y, 'kind' => $kind];
    }
};
$add('Championnat de France', 'titre', [1986, 1994, 2013, 2014, 2015, 2016, 2018, 2019, 2020, 2022, 2023, 2024, 2025, 2026]);
$add('Championnat de France', 'finale', [1989, 1993, 1996, 1997, 2000, 2004, 2012, 2017, 2021]);
$add('Coupe de France', 'titre', [1982, 1983, 1993, 1995, 1998, 2004, 2006, 2010, 2015, 2016, 2017, 2018, 2020, 2021, 2024, 2025]);
$add('Coupe de France', 'finale', [1985, 2003, 2008, 2011, 2019]);
$add('Coupe de la Ligue', 'titre', [1995, 1998, 2008, 2014, 2015, 2016, 2017, 2018, 2020]);
$add('Coupe de la Ligue', 'finale', [2000]);
$add('Trophée des champions', 'titre', [1995, 1998, 2013, 2014, 2015, 2016, 2017, 2018, 2019, 2020, 2022, 2023, 2024, 2025]);
$add('Trophée des champions', 'finale', [1986, 2004, 2006, 2010, 2021, 2026]);
$add('Ligue des champions', 'titre', [2025, 2026]);
$add('Ligue des champions', 'finale', [2020]);
$add('Ligue des champions', 'demi', [1995, 2021, 2024]);
$add('Coupe des coupes', 'titre', [1996]);
$add('Coupe des coupes', 'finale', [1997]);
$add('Coupe des coupes', 'demi', [1994]);
$add('Supercoupe de l\'UEFA', 'titre', [2025, 2026]);
$add('Supercoupe de l\'UEFA', 'finale', [1996]);
$add('Coupe intercontinentale', 'titre', [2025]);
$add('Coupe du monde des clubs', 'finale', [2025]);
$add('Coupe UEFA', 'demi', [1993]);
$add('Coupe Intertoto', 'titre', [2001]);
$add('Championnat de France de D2', 'titre', [1971]);
$add('Championnat de France de D2', 'finale', [1974]);
$add('Championnat de France de D3', 'finale', [1973]);
return $out;
