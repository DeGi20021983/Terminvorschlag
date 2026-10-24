@php($defaultMessage = 'Sie haben keine Berechtigung für diese Seite.')
@include('errors.layout', [
    'title' => 'Kein Zugriff',
    'message' => ($exception->getMessage() && ! str_contains($exception->getMessage(), 'unauthorized')) ? $exception->getMessage() : $defaultMessage,
])
