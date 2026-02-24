<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div className="flex min-h-svh flex-col items-center justify-center bg-muted p-6 md:p-10">
        <div className="w-full max-w-sm md:max-w-3xl">
            <Card class="overflow-hidden">
                <CardContent class="grid p-0 md:grid-cols-2">
                    <form @submit.prevent="submit" class="p-6 md:p-8">
                        <div class="flex flex-col gap-6">
                            <div class="flex flex-col items-center text-center">
                                <AppLogoIcon src="/images/logo.jpg" alt="Logo de la aplicación" class="w-10" />
                                <h1 class="text-2xl font-bold">Bienvenido de nuevo</h1>
                                <p class="text-muted-foreground">Ingresa a tu cuenta de ForUTex</p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input id="email" type="email" placeholder="email@ejemplo.com" v-model="form.email" required />
                            </div>
                            <div class="grid gap-2">
                                <div class="flex items-center">
                                    <Label for="password">Contraseña</Label>
                                </div>
                                <Input id="password" type="password" placeholder="Contraseña" v-model="form.password" required />
                            </div>
                            <div class="flex items-center justify-between" :tabindex="3">
                                <Label for="remember" class="flex items-center space-x-3">
                                    <input type="checkbox" id="remember" v-model="form.remember" :tabindex="4" />
                                    <span>Recuérdame</span>
                                </Label>
                            </div>
                            <Button type="submit" class="w-full" :disabled="form.processing"> Iniciar sesión </Button>
                        </div>
                    </form>
                    <div class="relative hidden bg-muted md:block">
                        <img
                            src="/images/login.png"
                            alt="Imagen"
                            class="absolute inset-0 h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
    <div class="flex flex-col gap-6"></div>
</template>

