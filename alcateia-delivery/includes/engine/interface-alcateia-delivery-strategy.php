<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
interface Alcateia_Delivery_Strategy { public function supports( $context ); public function resolve( $context ); }
