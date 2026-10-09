<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::livewire('/subscription', 'pages::subscription.checkout' )->name('subscription');
    Route::livewire('/c-notifications', 'pages::client.c-notifications' )->name('c-notifications');

});

Route::middleware(['auth', 'verified', 'subscription'])->group(function () {
    
    Route::livewire('c-dashboard', 'pages::app.c-dashboard')->name('c-dashboard');
    Route::livewire('/', 'pages::app.transactions.index')->name('home');
    Route::livewire('/categories', 'pages::app.transactions.categories')->name('categories');
    Route::livewire('/opening-balances', 'pages::app.transactions.opening-balances')->name('opening-balances');

});

Route::middleware(['auth', 'verified', 'permission:access-dashboard'])->group(function () {

    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('/notifications', 'pages::notifications.index' )->name('notifications');
    Route::livewire('/subscriptions', 'pages::subscription.admin.index' )->name('subscriptions');

    Route::livewire('/roles', 'pages::roles.index' )->name('roles');
    Route::livewire('/roles/create', 'pages::roles.create' )->name('roles.create');
    Route::livewire('/role/{id}', 'pages::roles.edit' )->name('roles.edit');

    Route::livewire('/users', 'pages::users.index' )->name('users')->middleware('permission:access-users');
    Route::livewire('/users/create', 'pages::users.create' )->name('users.create')->middleware('permission:create-users');
    Route::livewire('/user/{id}', 'pages::users.edit' )->name('user.edit')->middleware('permission:edit-users');

});


require __DIR__.'/settings.php';
