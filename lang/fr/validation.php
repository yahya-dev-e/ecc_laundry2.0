<?php

return [
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être du texte.',
    'email' => 'Indiquez une adresse e-mail valide.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'unique' => 'Cette valeur est déjà utilisée pour :attribute.',
    'min' => ['string' => 'Le champ :attribute doit contenir au moins :min caractères.', 'numeric' => 'Le champ :attribute doit être au moins :min.', 'array' => 'Sélectionnez au moins :min élément.'],
    'max' => ['string' => 'Le champ :attribute ne doit pas dépasser :max caractères.', 'numeric' => 'Le champ :attribute ne doit pas dépasser :max.', 'array' => 'Sélectionnez au maximum :max éléments.'],
    'attributes' => ['name' => 'nom complet', 'email' => 'adresse e-mail', 'password' => 'mot de passe', 'student_id' => 'numéro étudiant', 'room_number' => 'chambre'],
];
