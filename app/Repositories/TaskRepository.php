<?php

namespace App\Repositories;

use App\Models\Task;
use App\Repositories\Interfaces\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
class TaskRepository implements TaskRepositoryInterface
{
    public function list(int $limit, array $filters = []): Collection|LengthAwarePaginator
    {
        $query = Task::query();

        if (! empty($filters['priority']) && in_array($filters['priority'], Task::PRIORITIES, true)) {
            $query->where('priority', $filters['priority']);
        }

        return $query->paginate($limit);
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

    public function update($id, array $data): Task
    {
        $task = Task::findOrFail($id);
        $task->fill($data);
        $task->save();

        return $task;
    }

}
