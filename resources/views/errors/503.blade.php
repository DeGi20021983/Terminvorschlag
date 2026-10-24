@include('errors.layout', ['title' => 'Vorübergehend nicht verfügbar', 'message' => $exception->getMessage() ?: 'Der Dienst ist gerade nicht verfügbar.'])
