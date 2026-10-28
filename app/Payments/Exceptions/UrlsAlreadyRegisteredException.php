<?php

namespace App\Payments\Exceptions;

/** Safaricom refused because callback URLs are already registered for the shortcode. */
class UrlsAlreadyRegisteredException extends ProviderException {}
