<?php

file_put_contents('/tmp/magical_autoload_included.log', "included\n", FILE_APPEND);

spl_autoload_register(static function (string $class): void {
  file_put_contents('/tmp/magical_autoload_all.log', $class . PHP_EOL, FILE_APPEND);

  $prefix = 'Drupal\\magical_links\\';
  if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
    return;
  }

  $relative = substr($class, strlen($prefix));
  $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
  if (is_file($path)) {
    require_once $path;
  }
});
