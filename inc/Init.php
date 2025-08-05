<?php
namespace QQCPC;

final class Init {
    /**
     * Store all the classes inside an array
     * @return array Full list of classes
     */
    public static function get_services() {
        return [
            Base\Enqueue::class,
            Base\Updater::class,
            Pages\Admin::class,
            Api\OrderStatus::class
        ];
    }

    /**
     * Loop through the classes, initialize them, and call the register() method if it exists
     * @return void
     */
    public function register_services() {
        foreach (self::get_services() as $class) {
            $service = new $class();
            if (method_exists($service, 'register')) {
                $service->register();
            }
        }
    }
}
