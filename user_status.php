<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Status</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <div class="p-6">
    <!-- Card -->
    <div class="bg-white rounded-lg shadow-md">
      <div class="bg-pink-300 text-white text-lg font-semibold px-6 py-3 rounded-t-lg">
        User Status
      </div>

      <div class="p-6">
        <div class="flex justify-between mb-4">
          <div>
            <label for="filter" class="mr-2 font-medium">Filter:</label>
            <select id="filter" class="border rounded px-3 py-1">
              <option>All</option>
              <option>Active</option>
              <option>Inactive</option>
              <option>Suspended</option>
            </select>
          </div>
          <button class="bg-pink-400 hover:bg-pink-500 text-white font-medium px-4 py-2 rounded shadow">
            + Add Status
          </button>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-gray-100 text-left">
                <th class="px-4 py-2 border">Status ID</th>
                <th class="px-4 py-2 border">Status Name</th>
                <th class="px-4 py-2 border">Description</th>
                <th class="px-4 py-2 border">Date Created</th>
                <th class="px-4 py-2 border">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="px-4 py-2 border">1</td>
                <td class="px-4 py-2 border">Active</td>
                <td class="px-4 py-2 border">User can log in and access system</td>
                <td class="px-4 py-2 border">2025-01-05</td>
                <td class="px-4 py-2 border text-blue-600">
                  <a href="#" class="mr-2">Edit</a>
                  <a href="#" class="text-red-500">Delete</a>
                </td>
              </tr>
              <tr>
                <td class="px-4 py-2 border">2</td>
                <td class="px-4 py-2 border">Inactive</td>
                <td class="px-4 py-2 border">User cannot log in</td>
                <td class="px-4 py-2 border">2025-02-01</td>
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
