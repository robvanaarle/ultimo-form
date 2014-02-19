# Ultimo Form
Simple HTTP form handling

## Features
* Unset fields return empty value
* Array access
* Wrapping: custom formatting of required fields
* Validation

## Requirements
* PHP 5.3
* Ultimo Validation


## Usage

	$form = new \ultimo\form($_POST);
	$form->addValidator('title', 'NotEmpty');
	if ($form->isValid()) {
		// do something
    }


    