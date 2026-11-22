<?php
// Weather and route conditions for the shipments still on the way (feature 022). The only network fetch besides Ollama:
// MET Norway (weather, warnings) and Statens vegvesen's open traffic messages (roadworks, closures, ferries, road
// warnings). One attempt per source; a source that fails is replaced by rows marked demo.
// Run it on a schedule (the Docker image runs it every 15 minutes):
//
//   php scripts/conditions.php

declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/helpers.php';

$rows = array_values(array_filter((new PdoShipmentStore(db()))->all(), fn($r) => $r['status'] !== 'levert'));
foreach ([...Conditions::refresh($rows, time()), ...Ferries::refresh(time())] as $what => $result) {
    printf("%-9s %s\n", $what, $result);
}
