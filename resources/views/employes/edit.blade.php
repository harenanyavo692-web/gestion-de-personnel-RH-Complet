@extends('layouts.app')
@section('title', 'Modifier ' . $employe->nom_complet)

@section('content')
<x-page-header :title="$employe->nom_complet" subtitle="Modifier la fiche · {{ $employe->matricule }}" />
<form method="POST" action="{{ route('employes.update', $employe) }}" class="w-full">
    @csrf @method('PUT')
    @include('employes._form')
</form>
@endsection
