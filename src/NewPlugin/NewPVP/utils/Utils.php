<?php

declare(strict_types=1);

namespace NewPlugin\NewPVP\utils;

class Utils {
	public static function format(string $message, mixed... $args) : string {
		$i = 0;
		foreach ($args as $arg) {
			$message = str_replace("%$i", (string) $arg, $message);
			$i++;
		}
		return $message;
	}
}