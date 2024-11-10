<?php

namespace App\Repositories;

use App\Models\Task;
use App\Repositories\Interfaces\TaskRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class TaskRepository implements TaskRepositoryInterface
{
    public function create(array $data)
    {
        return Task::create($data);
    }

    public function delete($id)
    {
        return Task::destroy($id);
    }

    public function find($id)
    {
        return Task::find($id);
    }

    public function update(array $data, $id)
    {
        return Task::find($id)->update($data);
    }
} 