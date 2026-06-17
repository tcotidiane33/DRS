<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VmJobUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $jobId,
        public readonly string $status,
        public readonly int $progress,
        public readonly string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('proxmox-jobs');
    }

    public function broadcastAs(): string
    {
        return 'job.updated';
    }
}
