<?php

namespace App\Filament\Widgets;

use App\Models\Conversation;
use App\Models\Response;
use App\Models\Client;
use App\Models\Prompt;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConversationStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        return [
            Stat::make('Active Conversations', Conversation::where('status', 'active')->count())
                ->description('Open conversations with clients')
                ->descriptionIcon('heroicon-o-chat-bubble-oval-left-ellipsis')
                ->color('primary')
                ->chart(self::getActiveConversationsData()),

            Stat::make('Completed Conversations', Conversation::where('status', 'completed')->count())
                ->description('Successfully completed flows')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->chart(self::getCompletedConversationsData()),

            Stat::make('Client Responses', Response::count())
                ->description('Total messages received')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('info')
                ->chart(self::getResponsesData()),
                
            Stat::make('Active Clients', Client::has('conversations')->distinct()->count())
                ->description('Clients who have participated')
                ->descriptionIcon('heroicon-o-user')
                ->color('warning'),
                
            Stat::make('Prompts Used', Prompt::whereHas('responses')->count())
                ->description('Prompts that received responses')
                ->descriptionIcon('heroicon-o-chat-bubble-left-right')
                ->color('danger'),
        ];
    }
    
    private static function getActiveConversationsData(): array
    {
        // Get data for last 7 days
        return self::getLast7DaysData(Conversation::query()
            ->where('status', 'active')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date'));
    }
    
    private static function getCompletedConversationsData(): array
    {
        // Get data for last 7 days
        return self::getLast7DaysData(Conversation::query()
            ->where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date'));
    }
    
    private static function getResponsesData(): array
    {
        // Get data for last 7 days
        return self::getLast7DaysData(Response::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date'));
    }
    
    private static function getLast7DaysData($query): array
    {
        $days = collect(range(0, 6))
            ->map(function ($daysAgo) {
                return now()->subDays($daysAgo)->format('Y-m-d');
            })
            ->flip()
            ->map(function () {
                return 0;
            })
            ->toArray();
            
        $records = $query
            ->whereRaw('created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')
            ->get()
            ->pluck('count', 'date')
            ->toArray();
            
        // Merge the actual data with the empty days
        $data = array_replace($days, $records);
        
        // Return just the values (counts)
        return array_values($data);
    }
}
