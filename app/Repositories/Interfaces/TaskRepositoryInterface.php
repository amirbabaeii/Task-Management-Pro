<?php

namespace App\Repositories\Interfaces;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
    public function list(int $limit) : Collection|LengthAwarePaginator;
    public function create(array $data) : Task;
    public function update(array $data, $id) : Task;
    public function delete($id) : bool;
    public function find($id) : Task;

}
