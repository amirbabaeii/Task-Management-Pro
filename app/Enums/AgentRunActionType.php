<?php

namespace App\Enums;

enum AgentRunActionType: string
{
    case AddComment = 'add_comment';
    case AddChecklistItem = 'add_checklist_item';
    case ToggleChecklistItem = 'toggle_checklist_item';
    case UpdateProgress = 'update_progress';
    case ChangeStatus = 'change_status';
    case UpdateTaskFields = 'update_task_fields';

    public function canApplyAutomatically(): bool
    {
        return in_array($this, [
            self::AddComment,
            self::AddChecklistItem,
            self::ToggleChecklistItem,
            self::UpdateProgress,
            self::ChangeStatus,
        ], true);
    }
}
