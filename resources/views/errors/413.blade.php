@extends('errors.layout')

@section('code', '413')
@section('title', __('Upload too large'))
@section('message', __('The uploaded file exceeds the server upload limit. Please go back and choose a smaller file.'))

@section('actions')
    <button class="btn" type="button" onclick="history.back()">{{ __('Go back') }}</button>
@endsection
