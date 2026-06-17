<?php
if (!defined('ABSPATH')) { exit; }
class PSI_AI_Protection_Rules {
    private $protected_ids = array(9239,9242,9236,9231,9224,9220,9070,8983,8984,8985,8986,8988,8989,8990,8991,8992,8993,8994,8995,8996,8997,8998,8999,9000,9001,9002,9003,9004,8971,9006,9005,3935,3925,3913,8878,1848,1846,1814,35,25,13,1566);
    public function is_protected($post_id) { return in_array(absint($post_id), $this->protected_ids, true); }
    public function all() { return $this->protected_ids; }
}
