<?php

namespace App\Repositories;

use App\Models\Task;
use App\Repositories\Interfaces\TaskRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class TaskRepository implements TaskRepositoryInterface
{
    public function list(int $limit): Collection|LengthAwarePaginator
    {
        return Task::paginate($limit);
    }
    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function delete($id): bool
    {
        return Task::destroy($id);
    }

    public function find($id): Task
    {
        return Task::find($id);
    }

    public function update(array $data, $id): Task
    {
        return Task::find($id)->update($data);
    }

}
