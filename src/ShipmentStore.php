<?php
declare(strict_types=1);

/**
 * Where transport rows come from (DELIVERY 5.9). PdoShipmentStore reads the database; InMemoryShipmentStore holds
 * arrays, so Transport can be checked without a database.
 *
 * Row shapes, all timestamps as Unix seconds or null:
 *   all():  list of {id, ref, direction, status, origin_city, dest_city, is_demo, eta, planned_arrival, actual_arrival,
 *           fleet_no, vehicle_type, lat, lng, heading, observed_at, route: list<[lat, lng, source]>}
 *           lat/lng/heading/observed_at are the latest position, null without one.
 *   find(): {shipment: {id, ref, direction, status, origin_city, origin_point, dest_city, dest_point, container_ref,
 *           container_type, eta_source, weight_declared_kg, weight_measured_kg, delay_reason, is_demo, warehouse,
 *           planned_departure, actual_departure, eta, eta_calculated_at, planned_arrival, actual_arrival},
 *           vehicle: ?{fleet_no, type, make, model, model_year, capacity_kg, payload_kg, registration, is_demo},
 *           driver: ?{alias, driver_ref, phone, is_demo},
 *           items: list<{product_id, name, qty, weight_kg, weight_basis, handling_unit}>,
 *           positions: list<{lat, lng, heading, observed_at, received_at, source}> oldest first,
 *           route: list<[lat, lng, source]>} or null when the shipment does not exist.
 */
interface ShipmentStore
{
    public function all(): array;

    public function find(int $id): ?array;
}
