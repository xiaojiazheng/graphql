<?php

if (!function_exists('toArray')) {
  function toArray($obj)
  {
    return json_decode(json_encode($obj), true);
  }
}
