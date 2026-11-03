@props(['user'])
{{-- Photo de l'utilisateur, ou ses initiales. Taille et corps de texte via la classe passée (ex. h-8 w-8 text-xs). --}}
@if ($user->avatar_chemin)
    <img src="{{ route('avatars.show', $user) }}?v={{ $user->updated_at?->timestamp }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => 'rounded-full object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => 'flex items-center justify-center rounded-full bg-brand-soft font-semibold text-brand']) }}>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
@endif
