<?php
namespace app/RabbitEvents;

use Nuwber\Events\Event\Publishable;
use Nuwber\Events\Event\ShouldPublish;

class UserCreated implements ShouldPublish
{
    use Publishable;

    /**
     * @var User
     */
    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }


    public function publishEventKey(): string
    {
        return 'user.created';
    }

    public function toPublish(): array
    {
        return $this->user->toArray();
    }
}

$user = User::create(\Request::only(['first_name', 'last_name']));

// 1. use `Publishable` trait
UserCreated::publish($user);

// 2. Use helper function `publish` with event that implements `ShouldPublish`
$event = new UserCreated($user);

publish($event);

// 3. Old way. Just use `publish` helper function

publish('user.created', [$user->toArray()]);
