<?php

include "../divORM.php";

class Person {
	public $id = divORM::FIELD_TYPE_SERIAL;
	public $name;
}

// $orm = new divOrm(...);
divORM::createInstance([
	"type" => "pgsql",
	"host" => "localhost",
	"name" => "test",
	"user" => "postgres",
	"pass" => "postgres",
	"port" => 5432,
]);

// $orm->dropTable ...
divORM::map()->dropTable('person')
	->ifExists()->cascade()->prepare()->execute();

// $orm->designTable ...
divORM::map()->designTable('person')
		->addField('id', 'serial')
		->addField('name', 'varchar')
		->addField('inserted_date', 'timestamp without time zone')->defaultValue('now()')
		->create();

// $orm->insert ...
$result = divORM::map()->insert('person', ["name" => "Peter"], $st, " RETURNING id ");
$returning = $st->fetchObject();
var_dump($returning);

// $orm->select ...
$persons = divORM::map()->select()->from('person')->fetchObjects([],'Person');

var_dump($persons); // inserted_date if a field, but not a property in Person

$person = divORM::map()->select()->from('person')->fetchObject([],'Person');

var_dump($person);
