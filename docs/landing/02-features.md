---
view: components.packages.features
variant: compact
badge: Key Features
title: Likes that stay correct
subtitle: A small API on top of native Eloquent relations.
items:
  - icon: check-circle
    title: Results That Say What Changed
    text: '`like()` and `unlike()` return true only when they changed the table, so a repeated click is easy to tell apart.'
  - icon: lock
    title: Unique by the Database
    text: A unique index on the four columns keeps each liker and likeable pair to one row, under concurrent requests too.
  - icon: users
    title: Any Model Likes Any Model
    text: Users, teams or bots like posts, comments, products or each other. One model can use `CanLike` and `HasLikes` together.
  - icon: chart
    title: Native Counts and Eager Loading
    text: '`withCount(''likes'')`, `with(''likes.liker'')` and `whereLikedBy($user)` run in SQL, without loading every like.'
  - icon: tag
    title: Integer, UUID and ULID Keys
    text: Ids are stored as strings, so models with different key types share one table, with morph map aliases respected.
  - icon: shield-check
    title: Clean Deletes
    text: Deleting a model deletes its likes; a soft delete keeps them, so a restored model keeps its likes.
---
