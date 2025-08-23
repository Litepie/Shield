<?php

declare(strict_types=1);

namespace Litepie\Shield\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Litepie\Shield\Contracts\Role;

class RoleAttached
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Internally the HasRoles trait passes an array of role ids (eg: int's or uuid's)
     * Theoretically one could register the event to other places and pass an Eloquent record.
     * So a Listener should inspect the type of $rolesOrIds received before using.
     *
     * @param  array|int[]|string[]|Role|Role[]|Collection  $rolesOrIds
     */
    public function __construct(public Model $model, public mixed $rolesOrIds) {}
}
