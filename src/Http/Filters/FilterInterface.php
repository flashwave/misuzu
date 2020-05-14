<?php
namespace Misuzu\Http\Filters;

use Misuzu\Http\HttpResponseMessage;
use Misuzu\Http\HttpRequestMessage;

interface FilterInterface {
    public function process(HttpRequestMessage $request): ?HttpResponseMessage;
}
