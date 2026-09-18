import { router } from '@inertiajs/react';
import Swal from 'sweetalert2';

let sortDesc = false;

const ListHelper = {
    confirmAlert(
        url: string,
        message = 'Are you sure you want to do this?',
        confirmButtonText = 'Yes, proceed!',
    ): void {
        Swal.fire({
            title: message,
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText,
        }).then((result) => {
            if (result.isConfirmed) {
                router.get(url);
            }
        });
    },

    sortBy(column: string): void {
        sortDesc = !sortDesc;
        router.reload({
            method: 'get',
            data: { field_name: column, short_by: sortDesc ? 'desc' : 'asc' },
            replace: true,
        });
    },
};

export default ListHelper;
