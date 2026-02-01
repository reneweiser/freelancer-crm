<div class="space-y-4">
    <div class="p-4 bg-danger-50 dark:bg-danger-950 rounded-lg">
        <h4 class="font-medium text-danger-700 dark:text-danger-300 mb-2">Fehlermeldung</h4>
        <pre class="text-sm text-danger-600 dark:text-danger-400 whitespace-pre-wrap break-words">{{ $log->error_message }}</pre>
    </div>

    <div class="text-sm text-gray-500 dark:text-gray-400">
        <p><strong>Empfänger:</strong> {{ $log->recipient_email }}</p>
        <p><strong>Betreff:</strong> {{ $log->subject }}</p>
        <p><strong>Zeitpunkt:</strong> {{ $log->updated_at->format('d.m.Y H:i') }}</p>
    </div>
</div>
