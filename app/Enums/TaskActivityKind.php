<?php

namespace App\Enums;

enum TaskActivityKind: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case AssigneesChanged = 'assignees_changed';
    case CommentAdded = 'comment_added';
}
