<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Types</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <div class="p-6">
    <!-- Card -->
    <div class="bg-white rounded-lg shadow-md">
      <div class="bg-pink-300 text-white text-lg font-semibold px-6 py-3 rounded-t-lg">
        User Types
      </div>

      <div class="p-6">
        <div class="flex justify-between mb-4">
          <div>
            <label for="filter" class="mr-2 font-medium">Filter:</label>
            <select id="filter" class="border rounded px-3 py-1">
              <option>All</option>
              <option>Owner</option>
              <option>Staff</option>
              <option>Admin</option>
            </select>
          </div>
          <button class="bg-pink-400 hover:bg-pink-500 text-white font-medium px-4 py-2 rounded shadow">
            + Add User Type
          </button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-100 text-left">
                <th class="px-4 py-2 border">Type ID</th>
                <th class="px-4 py-2 border">Type Name</th>
                <th class="px-4 py-2 border">Description</th>
                <th class="px-4 py-2 border">Date Created</th>
                <th class="px-4 py-2 border">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="px-4 py-2 border">1</td>
                <td class="px-4 py-2 border">Owner</td>
                <td class="px-4 py-2 border">Has full system control</td>
                <td class="px-4 py-2 border">2025-01-01</td>
                <td class="px-4 py-2 border text-blue-600">
                  <a href="#" class="mr-2">Edit</a>
                  <a href="#" class="text-red-500">Delete</a>
                </td>
              </tr>
              <tr>
                <td class="px-4 py-2 border">2</td>
                <td class="px-4 py-2 border">Staff</td>
                <td class="px-4 py-2 border">Can manage products and orders</td>
                <td class="px-4 py-2 border">2025-02-15</td>
                <td class="px-4 py-2 border text-blue-600">
                  <a href="#" class="mr-2">Edit</a>
                  <a href="#" class="text-red-500">Delete</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
