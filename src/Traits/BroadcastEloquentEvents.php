<?php

namespace Nuwber\Events\Traits;

trait BroadcastEloquentEvents {

    /**
     * @var null
     */
    protected $broadcastEventKey = null;

    /**
     * @var bool
     */
    protected $broadcastCreatingEvent = true;

    /**
     * @var bool
     */
    protected $broadcastCreatedEvent = true;

    /**
     * @var bool
     */
    protected $broadcastUpdatingEvent = true;

    /**
     * @var bool
     */
    protected $broadcastUpdatedEvent = true;

    /**
     * @var bool
     */
    protected $broadcastSavingEvent = true;

    /**
     * @var bool
     */
    protected $broadcastSavedEvent = true;

    /**
     * @var bool
     */
    protected $broadcastDeletingEvent = true;

    /**
     * @var bool
     */
    protected $broadcastDeletedEvent = true;

    /**
     *
     */
    public static function bootBroadcastEloquentEvents()
    {
        static::creating(function ($model) {
            if ($model->broadcastCreatingEvent) {
                $model->broadcastEloquentEvent('creating');
            }
        });

        static::created(function ($model) {
            if ($model->broadcastCreatedEvent) {
                $model->broadcastEloquentEvent('created');
            }
        });

        static::saving(function ($model) {
            if ($model->broadcastSavingEvent) {
                $model->broadcastEloquentEvent('saving');
            }
        });

        static::saved(function ($model) {
            if ($model->broadcastSavedEvent) {
                $model->broadcastEloquentEvent('saved');
            }
        });

        static::updating(function ($model) {
            if ($model->broadcastUpdatingEvent) {
                $model->broadcastEloquentEvent('updating');
            }
        });

        static::updated(function ($model) {
            if ($model->broadcastUpdatedEvent) {
                $model->broadcastEloquentEvent('updated');
            }
        });

        static::deleting(function ($model) {
            if ($model->broadcastDeletingEvent) {
                $model->broadcastEloquentEvent('deleting');
            }
        });

        static::deleted(function ($model) {
            if ($model->broadcastDeletedEvent) {
                $model->broadcastEloquentEvent('deleted');
            }
        });
    }


    /**
     * @return |null
     */
    public function getBroadcastEventKey()
    {
        if ($this->broadcastEventKey) {
            return $this->broadcastEventKey;
        }

        return kebab_case(str_singular($this->getTable()));
    }


    /**
     * @param string $event
     */
    public function broadcastEloquentEvent(string $event)
    {
        $event = $this->getBroadcastEventKey() . '.' . $event;

        fire($event, $this->formatBroadcastModel());
    }

    /**
     * @return mixed
     */
    public function formatBroadcastModel()
    {
        $broadcastModel = $this->toArray();

        return $broadcastModel;
    }

}