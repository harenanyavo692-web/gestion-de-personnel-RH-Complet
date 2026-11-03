@extends('layouts.app')
@section('title', 'Nouvel employé')

@section('content')
<x-page-header title="Nouvel employé" subtitle="Créer une fiche du personnel" />
<form method="POST" action="{{ route('employes.store') }}" class="w-full">
    @csrf
    @include('employes._form')
</form>
@endsection
