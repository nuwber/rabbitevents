<?php

namespace RabbitEvents\Publisher\Support;

trait PublishableAfterCommit
{
    public bool $afterCommit = false;
}