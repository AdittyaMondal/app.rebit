@extends('documentLayout::index')

@section('pageContent')
    @includeIf('document::guidelines.i18n.' . app()->getLocale())
@endsection
