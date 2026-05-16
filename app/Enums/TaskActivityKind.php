<?php

namespace App\Enums;

enum TaskActivityKind: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case AssigneesChanged = 'assignees_changed';
    case CommentAdded = 'comment_added';
    case Archived = 'archived';
    case Restored = 'restored';
    case ChecklistItemAdded = 'checklist_item_added';
    case ChecklistItemCompleted = 'checklist_item_completed';
    case ChecklistItemReopened = 'checklist_item_reopened';
    case ChecklistItemRenamed = 'checklist_item_renamed';
    case ChecklistItemDeleted = 'checklist_item_deleted';
}
