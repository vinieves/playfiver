<template>
    <BaseLayout>
        <LoadingComponent :isLoading="isLoading">
            <div class="flex justify-center items-center h-full">
                <a v-if="setting" href="/" class="logo-animation"> <!-- Adicionando a classe items-center para centralizar verticalmente -->
                    <img :src="`/storage/` + setting.software_logo_black" alt="" class="h-10 mr-3 block dark:hidden" />
                    <img :src="`/storage/` + setting.software_logo_white" alt="" class="h-10 mr-3 hidden dark:block" />
                </a>
            </div>
        </LoadingComponent>

        <div v-if="!isLoading" class="md:w-5/6 2xl:w-5/6 mx-auto my-16 p-4">
            <HeaderComponent>
                <template #header>
                    {{ $t('List of') }} <span class=" bg-blue-100 text-blue-800 text-2xl font-semibold me-2 px-2.5 py-0.5 rounded dark:bg-blue-200 dark:text-blue-800 ms-2">{{ $t('Records') }}</span>
                </template>

            </HeaderComponent>

            <div>

            </div>
        </div>
    </BaseLayout>
</template>


<script>

import BaseLayout from "@/Layouts/BaseLayout.vue";
import LoadingComponent from "@/Components/UI/LoadingComponent.vue";
import HeaderComponent from "@/Components/UI/HeaderComponent.vue";
import HttpApi from "@/Services/HttpApi.js";

export default {
    props: [],
    components: {HeaderComponent, LoadingComponent, BaseLayout },
    data() {
        return {
            isLoading: false,
            games: null,
        }
    },
    setup(props) {


        return {};
    },
    computed: {

    },
    mounted() {

    },
    methods: {

    },
    async created() {

    },
    watch: {

    },
};
</script>

<style scoped>

</style>
